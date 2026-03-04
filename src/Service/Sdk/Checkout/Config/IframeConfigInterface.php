<?php
declare(strict_types=1);


namespace Webbhuset\CollectorCheckout\Service\Sdk\Checkout\Config;

interface IframeConfigInterface
{
    public function getSrc(string $mode) : string;
    public function getDataToken(): string;
    public function getDataLang(): ?string;
    public function getDataPadding(): ?string;
    public function getDataContainerId(): ?string;
    public function getDataActionColor(): ?string;
    public function getDataActionTextColor(): ?string;
}
