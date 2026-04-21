<?php
declare(strict_types=1);


namespace Webbhuset\CollectorCheckout\Service\Sdk\Checkout;

use Webbhuset\CollectorCheckout\Service\Sdk\Checkout\Errors\ValidationError;
use Webbhuset\CollectorCheckout\Service\Sdk\Checkout\Checkout\Purchase\Result;
use Webbhuset\CollectorCheckout\Service\Sdk\Checkout\Checkout\Purchase\ResultFactory;
use Webbhuset\CollectorCheckout\Service\Sdk\Checkout\Checkout\Status;
use Webbhuset\CollectorCheckout\Service\Sdk\Checkout\Checkout\StatusFactory;
use Webbhuset\CollectorCheckout\Service\Sdk\Checkout\Checkout\Purchase;
use Webbhuset\CollectorCheckout\Service\Sdk\Checkout\Checkout\PurchaseFactory;
use Webbhuset\CollectorCheckout\Service\Sdk\Checkout\Checkout\Cart;
use Webbhuset\CollectorCheckout\Service\Sdk\Checkout\Checkout\CartFactory;
use Webbhuset\CollectorCheckout\Service\Sdk\Checkout\Checkout\Cart\Item as CartItem;
use Webbhuset\CollectorCheckout\Service\Sdk\Checkout\Checkout\Cart\ItemFactory as CartItemFactory;
use Webbhuset\CollectorCheckout\Service\Sdk\Checkout\Checkout\Order\Item as OrderItem;
use Webbhuset\CollectorCheckout\Service\Sdk\Checkout\Checkout\Order\ItemFactory as OrderItemFactory;
use Webbhuset\CollectorCheckout\Service\Sdk\Checkout\Checkout\Fees;
use Webbhuset\CollectorCheckout\Service\Sdk\Checkout\Checkout\FeesFactory;
use Webbhuset\CollectorCheckout\Service\Sdk\Checkout\Checkout\Fees\Fee;
use Webbhuset\CollectorCheckout\Service\Sdk\Checkout\Checkout\Fees\FeeFactory;
use Webbhuset\CollectorCheckout\Service\Sdk\Checkout\Checkout\Order;
use Webbhuset\CollectorCheckout\Service\Sdk\Checkout\Checkout\OrderFactory;
use Webbhuset\CollectorCheckout\Service\Sdk\Checkout\Checkout\Shipping;
use Webbhuset\CollectorCheckout\Service\Sdk\Checkout\Checkout\ShippingFactory;
use Webbhuset\CollectorCheckout\Service\Sdk\Checkout\Checkout\Customer\AbstractCustomer;
use Webbhuset\CollectorCheckout\Service\Sdk\Checkout\Checkout\Customer\PrivateCustomer;
use Webbhuset\CollectorCheckout\Service\Sdk\Checkout\Checkout\Customer\BusinessCustomer;
use Webbhuset\CollectorCheckout\Service\Sdk\Checkout\Checkout\Customer\PrivateCustomerFactory;
use Webbhuset\CollectorCheckout\Service\Sdk\Checkout\Checkout\Customer\BusinessCustomerFactory;
use Webbhuset\CollectorCheckout\Service\Sdk\Checkout\Checkout\Customer\PrivateAddressFactory;
use Webbhuset\CollectorCheckout\Service\Sdk\Checkout\Checkout\Customer\BusinessAddressFactory;

class CheckoutData
{
    private ?AbstractCustomer $customer = null;
    private string $customerType = '';
    private string $countryCode = '';
    private ?Status $status = null;
    private string $paymentName = '';
    private string $reference = '';
    private ?Cart $cart = null;
    private ?Fees $fees = null;
    private ?Purchase $purchase = null;
    private ?Order $order = null;
    private ?Shipping $shipping = null;
    /**
     * @var array<int, array<string, mixed>>
     */
    private array $customField = [];
    private FeesFactory $feesFactory;
    private FeeFactory $feeFactory;
    private CartFactory $cartFactory;
    private CartItemFactory $cartItemFactory;
    private OrderFactory $orderFactory;
    private OrderItemFactory $orderItemFactory;
    private ShippingFactory $shippingFactory;
    private PurchaseFactory $purchaseFactory;
    private ResultFactory $resultFactory;
    private StatusFactory $statusFactory;
    private PrivateCustomerFactory $privateCustomerFactory;
    private BusinessCustomerFactory $businessCustomerFactory;
    private BusinessAddressFactory $businessAddressFactory;
    private PrivateAddressFactory $privateAddressFactory;

    public function __construct(
        array $response,
        FeesFactory $feesFactory,
        FeeFactory $feeFactory,
        CartFactory $cartFactory,
        CartItemFactory $cartItemFactory,
        OrderFactory $orderFactory,
        OrderItemFactory $orderItemFactory,
        ShippingFactory $shippingFactory,
        PurchaseFactory $purchaseFactory,
        ResultFactory $resultFactory,
        StatusFactory $statusFactory,
        PrivateCustomerFactory $privateCustomerFactory,
        BusinessCustomerFactory $businessCustomerFactory,
        BusinessAddressFactory $businessAddressFactory,
        PrivateAddressFactory $privateAddressFactory
    ) {
        $this->feesFactory = $feesFactory;
        $this->feeFactory = $feeFactory;
        $this->cartFactory = $cartFactory;
        $this->cartItemFactory = $cartItemFactory;
        $this->orderFactory = $orderFactory;
        $this->orderItemFactory = $orderItemFactory;
        $this->shippingFactory = $shippingFactory;
        $this->purchaseFactory = $purchaseFactory;
        $this->resultFactory = $resultFactory;
        $this->statusFactory = $statusFactory;
        $this->privateCustomerFactory = $privateCustomerFactory;
        $this->businessCustomerFactory = $businessCustomerFactory;
        $this->businessAddressFactory = $businessAddressFactory;
        $this->privateAddressFactory = $privateAddressFactory;
        $this->fromResponse($response);
    }

    public function getCustomFieldNewsletter():array
    {
        $fields = $this->getFields($this->customField);
        if (!isset($fields['newsConsent'])) {
            return [];
        }
        return $fields['newsConsent'];
    }

    public function getCustomFieldComment():array
    {
        $fields = $this->getFields($this->customField);
        if (!isset($fields['comments'])) {
            return [];
        }
        return $fields['comments'];
    }

    public function getFields(array $customFields):array
    {
        if (!isset($this->customField[0]['fields'])){
            return [];
        }
        $result = [];
        $fields = $this->customField[0]['fields'];
        foreach ($fields as $field) {
            if(isset($field['id']) && isset($field['value'])) {
                $result[$field['id']] = [
                    'id' => $field['id'],
                    'value' => $field['value'],
                ];
            }
        }
        return $result;
    }

    public function getCustomerType() : string
    {
        return $this->customerType;
    }

    public function getCustomer(): ?AbstractCustomer
    {
        return $this->customer;
    }

    public function getCountryCode() : string
    {
        return $this->countryCode;
    }

    public function getStatus() : Status
    {
        return $this->status;
    }

    /**
     * Please note: These values are subject to change and we strongly encourage you to handle them dynamically.
     * New payment names may emerge without any notice and you should design your system to handle this situation.
     *
     * @return string
     */
    public function getPaymentName() : string
    {
        return $this->paymentName;
    }

    public function getReference() : string
    {
        return $this->reference;
    }

    public function getCart(): ?Cart
    {
        return $this->cart;
    }

    public function getFees(): ?Fees
    {
        return $this->fees;
    }

    public function getPurchase(): ?Purchase
    {
        return $this->purchase;
    }

    public function getOrder(): ?Order
    {
        return $this->order;
    }

    public function getShipping(): ?Shipping
    {
        return $this->shipping;
    }

    private function fromResponse(array $response) : CheckoutData
    {
        $data = isset($response['data']) ? $response['data'] : false;
        if (!$data) {
            throw new ValidationError('Unexpected data in response');
        }

        $this->customer         = $this->customerFromArray($data);
        $this->customerType     = $data['customerType'] ?? '';
        $this->countryCode      = $data['countryCode'] ?? '';
        $this->status           = $this->statusFromArray($data);
        $this->paymentName      = $data['paymentName'] ?? '';
        $this->reference        = $data['reference'] ?? '';
        $this->cart             = $this->cartFromArray($data);
        $this->fees             = $this->feesFromArray($data);
        $this->purchase         = $this->purchaseFromArray($data);
        $this->order            = $this->orderFromArray($data);
        $this->shipping         = $this->shippingFromArray($data);
        $this->customField      = $data['customFields'] ?? [];

        return $this;
    }

    private function feesFromArray(array $data): ?Fees
    {
        if (empty($data['fees'])) {
            return null;
        }

        $shippingFeeData    = $data['fees']['shipping'] ?? [];
        $shippingFee        = $this->feeFromArray($shippingFeeData);

        $invoiceFeeData     = $data['fees']['directInvoiceFee'] ?? [];
        $directInvoiceFee   = $this->feeFromArray($invoiceFeeData);

        return $this->feesFactory->create([
            'shippingFee' => $shippingFee,
            'directInvoiceFee' => $directInvoiceFee,
        ]);
    }

    private function feeFromArray(array $data): ?Fee
    {
        if (empty($data)) {
            return null;
        }

        return $this->feeFactory->create([
            'id' => isset($data['id']) ? (string) $data['id'] : '',
            'description' => isset($data['description']) ? (string) $data['description'] : '',
            'unitPrice' => isset($data['unitPrice']) ? (float) $data['unitPrice'] : 0,
            'vat' => isset($data['vat']) ? (float) $data['vat'] : 0,
            'sku' => isset($data['sku']) ? (string) $data['sku'] : '',
        ]);
    }

    private function cartFromArray(array $data): ?Cart
    {
        if (empty($data['cart'])) {
            return null;
        }

        $data           = $data['cart'] ?? [];
        $totalAmount    = isset($data['totalAmount']) ? (int) $data['totalAmount'] : null;
        $itemsData      = $data['items'] ?? [];
        $items          = [];
        foreach ($itemsData as $itemData) {
            $items[] = $this->cartItemFactory->create([
                'id' => isset($itemData['id']) ? (string) $itemData['id'] : '',
                'description' => isset($itemData['description']) ? (string) $itemData['description'] : '',
                'unitPrice' => isset($itemData['unitPrice']) ? (float) $itemData['unitPrice'] : 0,
                'quantity' => isset($itemData['quantity']) ? (int) $itemData['quantity'] : 0,
                'vat' => isset($itemData['vat']) ? (float) $itemData['vat'] : 0,
            ]);
        }

        return $this->cartFactory->create([
            'items' => $items,
            'totalAmount' => $totalAmount,
        ]);
    }

    private function orderFromArray(array $data): ?Order
    {
        if (empty($data['order'])) {
            return null;
        }

        $data           = $data['order'] ?? [];
        $totalAmount    = isset($data['totalAmount']) ? (int) $data['totalAmount'] : 0;
        $itemsData      = $data['items'] ?? [];
        $items          = [];
        foreach ($itemsData as $itemData) {
            $items[] = $this->orderItemFactory->create([
                'id' => isset($itemData['id']) ? (string) $itemData['id'] : '',
                'description' => isset($itemData['description']) ? (string) $itemData['description'] : '',
                'unitPrice' => isset($itemData['unitPrice']) ? (float) $itemData['unitPrice'] : 0,
                'quantity' => isset($itemData['quantity']) ? (int) $itemData['quantity'] : 0,
                'vat' => isset($itemData['vat']) ? (float) $itemData['vat'] : 0,
                'sku' => isset($itemData['sku']) ? (string) $itemData['sku'] : '',
            ]);
        }

        return $this->orderFactory->create([
            'items' => $items,
            'totalAmount' => $totalAmount,
        ]);
    }

    private function shippingFromArray(array $data) : Shipping
    {
        $shippingData = isset($data['shipping'])
            ? $data['shipping']
            : [];

        return $this->shippingFactory->create([
            'shippingData' => $shippingData,
        ]);
    }

    private function purchaseFromArray(array $data) : Purchase
    {
        $orderId = isset($data['order']['orderId']) ? $data['order']['orderId'] : '';
        $data = $data['purchase'] ?? [];
        $result = $this->resultFactory->create([
            'result' => $data['result'] ?? Result::PRELIMINARY,
        ]);

        return $this->purchaseFactory->create([
            'amountToPay' => $data['amountToPay'] ?? 0,
            'paymentName' => $data['paymentName'] ?? '',
            'invoiceDeliveryMethod' => $data['invoiceDeliveryMethod'] ?? '',
            'purchaseIdentifier' => $data['purchaseIdentifier'] ?? '',
            'orderId' => $orderId,
            'result' => $result,
        ]);
    }

    private function statusFromArray(array $data) : Status
    {
        return $this->statusFactory->create([
            'status' => $data['status'] ?? Status::INITIALIZED,
        ]);
    }

    private function customerFromArray(array $data): ?AbstractCustomer
    {
        $customerType = $data['customerType'] ?? false;

        if (!$customerType) {
            return null;
        }

        if ($customerType === AbstractCustomer::BUSINESS_CUSTOMER) {
            $customerData       = $data['businessCustomer'] ?? [];

            $companyName        = $customerData['companyName'] ?? '';
            $orgNumber          = $customerData['organizationNumber'] ?? '';
            $invoiceReference   = $customerData['invoiceReference'] ?? '';
            $invoiceTag         = $customerData['invoiceTag'] ?? '';
            $email              = $customerData['email'] ?? '';
            $firstName          = $customerData['firstName'] ?? '';
            $lastName           = $customerData['lastName'] ?? '';
            $mobilePhoneNumber  = $customerData['mobilePhoneNumber'] ?? '';
            $invoiceAddress     = $this->businessAddressFromArray($customerData['invoiceAddress'] ?? []);
            $deliveryAddress    = $this->businessAddressFromArray($customerData['deliveryAddress'] ?? []);

            return $this->businessCustomerFactory->create([
                'companyName' => $companyName,
                'organizationNumber' => $orgNumber,
                'invoiceReference' => $invoiceReference,
                'invoiceTag' => $invoiceTag,
                'email' => $email,
                'firstName' => $firstName,
                'lastName' => $lastName,
                'mobilePhoneNumber' => $mobilePhoneNumber,
                'invoiceAddress' => $invoiceAddress,
                'deliveryAddress' => $deliveryAddress,
            ]);
        }

        $customerData                 = $data['customer'] ?? [];
        $email                        = $customerData['email'] ?? '';
        $mobilePhoneNumber            = $customerData['mobilePhoneNumber'] ?? '';
        $deliveryMobilePhoneNumber    = $customerData['deliveryContactInformation']['mobilePhoneNumber'] ?? '';
        $deliveryAddress              = $this->privateAddressFromArray($customerData['deliveryAddress'] ?? []);
        $billingAddress               = $this->privateAddressFromArray($customerData['billingAddress'] ?? []);
        $nationalIdentificationNumber = $customerData['nationalIdentificationNumber'] ?? '';

        return $this->privateCustomerFactory->create([
            'email' => $email,
            'mobilePhoneNumber' => $mobilePhoneNumber,
            'deliveryMobilePhoneNumber' => $deliveryMobilePhoneNumber,
            'invoiceAddress' => $billingAddress,
            'deliveryAddress' => $deliveryAddress,
            'nationalIdentificationNumber' => $nationalIdentificationNumber,
        ]);
    }


    private function businessAddressFromArray(array $data): \Webbhuset\CollectorCheckout\Service\Sdk\Checkout\Checkout\Customer\BusinessAddress
    {
        $companyName    = $data['companyName'] ?? '';
        $address        = $data['address'] ?? '';
        $address2       = $data['address2'] ?? null;
        $coAddress      = $data['coAddress'] ?? null;
        $postalCode     = $data['postalCode'] ?? '';
        $city           = $data['city'] ?? '';
        $country        = $data['country'] ?? '';
        $firstName      = $data['firstName'] ?? '';
        $lastName       = $data['lastName'] ?? '';

        return $this->businessAddressFactory->create([
            'companyName' => $companyName,
            'address' => $address,
            'postalCode' => $postalCode,
            'city' => $city,
            'country' => $country,
            'address2' => $address2,
            'coAddress' => $coAddress,
            'firstName' => $firstName,
            'lastName' => $lastName,
        ]);
    }

    private function privateAddressFromArray(array $data): \Webbhuset\CollectorCheckout\Service\Sdk\Checkout\Checkout\Customer\PrivateAddress
    {
        $firstName      = $data['firstName'] ?? '';
        $lastName       = $data['lastName'] ?? '';
        $address        = $data['address'] ?? '';
        $address2       = $data['address2'] ?? null;
        $coAddress      = $data['coAddress'] ?? null;
        $postalCode     = $data['postalCode'] ?? '';
        $city           = $data['city'] ?? '';
        $country        = $data['country'] ?? '';

        return $this->privateAddressFactory->create([
            'firstName' => $firstName,
            'lastName' => $lastName,
            'address' => $address,
            'postalCode' => $postalCode,
            'city' => $city,
            'country' => $country,
            'address2' => $address2,
            'coAddress' => $coAddress,
        ]);
    }
}
