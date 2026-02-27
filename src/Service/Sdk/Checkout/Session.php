<?php
declare(strict_types=1);


namespace Webbhuset\CollectorCheckout\Service\Sdk\Checkout;

use Webbhuset\CollectorCheckout\Config\Source\Customer\DefaultType;
use Webbhuset\CollectorCheckout\Service\Sdk\Checkout\Adapter\AdapterInterface;
use Webbhuset\CollectorCheckout\Service\Sdk\Checkout\Checkout\Cart;
use Webbhuset\CollectorCheckout\Service\Sdk\Checkout\Checkout\Customer\InitializeCustomer;
use Webbhuset\CollectorCheckout\Service\Sdk\Checkout\Checkout\Fees;
use Webbhuset\CollectorCheckout\Service\Sdk\Checkout\Config\ConfigInterface;
use Webbhuset\CollectorCheckout\Service\Sdk\Checkout\CheckoutDataFactory;

class Session
{
    private AdapterInterface $adapter;
    private ?string $privateId = null;
    private ?string $publicToken = null;
    private ?string $paymentUri = null;
    private ?string $sessionId = null;
    private ?string $expiresAt = null;
    private ?CheckoutData $checkoutData = null;
    private CheckoutDataFactory $checkoutDataFactory;

    /**
     * @var array<int, string>
     */
    private array $validCountryCodes = [
        'SE',
        'NO',
        'FI',
        'DK',
        'DE',
    ];

    public function __construct(
        AdapterInterface $adapter,
        CheckoutDataFactory $checkoutDataFactory
    ) {
        $this->adapter = $adapter;
        $this->checkoutDataFactory = $checkoutDataFactory;
    }

    public function initialize(
        ConfigInterface $config,
        $fees,
        Cart $cart,
        string $countryCode,
        ?InitializeCustomer $customer = null,
        ?string $reference = null
    ): self {
        if ($fees instanceof Fees) {
            $fees = $fees->toArray();
        } elseif (!is_array($fees)) {
            throw new \InvalidArgumentException('Fees must be an instance of Fees or an array.');
        }

        $cart = $cart->toArray();

        if (!in_array($countryCode, $this->validCountryCodes)) {
            $codes = implode(', ', $this->validCountryCodes);
            throw new ValidationError("Country code not valid. Must be one of {$codes}");
        }


        $data = [
            "storeId"                   => $config->getStoreId(),
            "countryCode"               => $countryCode,
            "reference"                 => $reference,
            "redirectPageUri"           => $config->getRedirectPageUri(),
            "merchantTermsUri"          => $config->getMerchantTermsUri(),
            "notificationUri"           => $config->getNotificationUri(),
            "validationUri"             => $config->getValidationUri(),
            'fees'                      => $fees,
            'cart'                      => $cart,
        ];
        $customFields = $config->getCustomFields();
        if (!empty($customFields)) {
            $data['customFields'] = $customFields;
        }

        if ($config->getProfileName()) {
            $data['profileName'] = $config->getProfileName();
        }

        if (empty($data['fees'])) {
            unset($data['fees']);
        }
        if (isset($fees['provider'])) {
            unset($data['fees']);
            $data["shipping"] = $fees;
        }

        if ($customer) {
            $customerData = [
                'email'                         => $customer->getEmail(),
                'mobilePhoneNumber'             => $customer->getMobilePhoneNumber(),
                'nationalIdentificationNumber'  => $customer->getNationalIdentificationNumber(),
                'deliveryAddress'               => $customer->getDeliveryAddress(),
            ];
            if ((int)$customer->getCustomerType() === DefaultType::PRIVATE_CUSTOMERS) {
                $data['privateCustomerPrefill'] = $customerData;
                if (isset($data['privateCustomerPrefill']['deliveryAddress'])
                    && empty($data['privateCustomerPrefill']['deliveryAddress'])) {
                    unset($data['privateCustomerPrefill']['deliveryAddress']);
                }
            }
            if ((int)$customer->getCustomerType() === DefaultType::BUSINESS_CUSTOMERS) {
                $data['businessCustomerPrefill'] = $customerData;
                $data['businessCustomerPrefill']['buyer'] = $this->getBuyerInformation($customerData);
                $data['businessCustomerPrefill']['organizationNumber'] = $customer->getNationalIdentificationNumber() ?? '';
                unset($data['businessCustomerPrefill']['deliveryAddress']['firstName']);
                unset($data['businessCustomerPrefill']['deliveryAddress']['lastName']);
                unset($data['businessCustomerPrefill']['mobilePhoneNumber']);
                unset($data['businessCustomerPrefill']['nationalIdentificationNumber']);
                if (isset($data['businessCustomerPrefill']['deliveryAddress'])
                    && empty($data['businessCustomerPrefill']['deliveryAddress'])) {
                    unset($data['businessCustomerPrefill']['deliveryAddress']);
                }
            }
        }
        $response = $this->adapter->initializeCheckout($data);

        if (isset($response['data']['privateId'])) {
            $this->privateId = $response['data']['privateId'];
        }

        if (isset($response['data']['publicToken'])) {
            $this->publicToken = $response['data']['publicToken'];
        }

        if (isset($response['data']['expiresAt'])) {
            $this->expiresAt = $response['data']['expiresAt'];
        }

        if (isset($response['data']['paymentUri'])) {
            $this->paymentUri = $response['data']['paymentUri'];
        }
        if (isset($response['id'])) {
            $this->sessionId = $response['id'];
        }

        return $this;
    }

    public function getPaymentUri():string
    {
        return (string) $this->paymentUri;
    }

    public function getSessionId():string
    {
        return (string) $this->sessionId;
    }

    public function updateCart(Cart $cart): self
    {
        $cart       = $cart->toArray();
        $response   = $this->adapter->updateCart($cart, $this->getPrivateId());

        return $this;
    }

    public function updateFees(Fees $fees): self
    {
        $fees       = $fees->toArray();
        $response   = $this->adapter->updateFees($fees, $this->getPrivateId());

        return $this;
    }

    /**
     * @param array<string, mixed> $customerData
     * @return array<string, string>
     */
    public function getBuyerInformation(array $customerData): array
    {
        return [
            'firstName' => $customerData['deliveryAddress']['firstName'] ?? '',
            'lastName' => $customerData['deliveryAddress']['lastName'] ?? '',
            'email' => $customerData['email'] ?? '',
            'mobilePhoneNumber' => $customerData['mobilePhoneNumber'] ?? '',
        ];
    }


    public function setOrderReference(string $reference): self
    {
        $response = $this->adapter->setOrderReference($reference, $this->getPrivateId());

        return $this;
    }

    public function setPrivateId(string $privateId) : Session
    {
        $this->privateId = $privateId;

        return $this;
    }

    public function load(string $privateId) : Session
    {
        $response = $this->adapter->acquireInformation($privateId);

        $this->privateId = $privateId;
        $this->checkoutData = $this->checkoutDataFactory->create(['response' => $response]);

        return $this;
    }

    public function getCheckoutData() : ?CheckoutData
    {
        return $this->checkoutData;
    }

    public function getPublicToken(): ?string
    {
        return $this->publicToken;
    }

    public function getPrivateId(): ?string
    {
        return $this->privateId;
    }

    public function getExpiresAt(): ?string
    {
        return $this->expiresAt;
    }
}
