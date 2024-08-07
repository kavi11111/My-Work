<?php

namespace GameNation\Lib\Facebook;

use FacebookAds\Api;
use FacebookAds\Logger\CurlLogger;
use FacebookAds\Object\ServerSide\Content;
use FacebookAds\Object\ServerSide\CustomData;
use FacebookAds\Object\ServerSide\Event;
use FacebookAds\Object\ServerSide\EventRequest;
use FacebookAds\Object\ServerSide\EventResponse;
use FacebookAds\Object\ServerSide\UserData;
use GameNation\Lib\Facebook\Models\EventContext;
use Google\Exception;
use Lib\Properties;
use Lib\Utilities;

require_once __DIR__ . '/vendor/autoload.php';

class ConversionsAPI
{
    private array $apiConfig = [];
    private array $events = [];
    private $countryCodes = [];
    private $emailPattern = '/^[a-zA-Z0-9._%+-]+@[a-zA-Z0-9.-]+\.[a-zA-Z]{2,7}$/';

    function __construct()
    {
        $this->apiConfig = Properties::GetClassBundle('FacebookConversionsAPI');
        if ($this->apiConfig === null) {
            throw new Exception('Unable to load api configurations');
        }
        $this->apiConfig = $this->apiConfig['FacebookConversionsAPI'];
        // var_dump($this->apiConfig);
        $api = $this->APIInstance();
        $api->setLogger(new CurlLogger());
        $this->countryCodes = Utilities\AddressUtility::GetSupportedCountryNameToCodeMap(true);
    }

    public function QueueEvent(EventContext $context): ConversionsAPI
    {
        $userData = (new UserData())
            ->setClientUserAgent($context->userAgent)
            ->setClientIpAddress($context->clientIP)
            ->setFbc($context->fbc)
            ->setFbp($context->fbp);
        if (isset($context->adHoc['firstName']) && trim($context->adHoc['firstName']) !== '') {
            $userData->setFirstName($context->adHoc['firstName']);
        }

        if (isset($context->adHoc['lastName']) && trim($context->adHoc['lastName']) !== '') {
            $userData->setLastName($context->adHoc['lastName']);
        }

        if (isset($context->adHoc['email']) && trim($context->adHoc['email']) !== '' && preg_match($this->emailPattern, $context->adHoc['email'])) {
            $userData->setEmail($context->adHoc['email']);
        }

        if (isset($context->adHoc['phoneNumber']) && trim($context->adHoc['phoneNumber']) !== '') {
            $userData->setPhone($context->adHoc['phoneNumber']);
        }

        if (isset($context->adHoc['zipCode']) && trim($context->adHoc['zipCode']) !== '') {
            $userData->setZipCode($context->adHoc['zipCode']);
        }

        if (isset($context->adHoc['city']) && trim($context->adHoc['city']) !== '') {
            $userData->setCity($context->adHoc['city']);
        }

        if (isset($context->adHoc['state']) && trim($context->adHoc['state']) !== '') {
            $userData->setState($context->adHoc['state']);
        }

        if (isset($context->adHoc['country']) && trim($context->adHoc['country']) !== '') {
            $userData->setCountryCode($this->countryCodes[strtolower($context->adHoc['country'] ?? 'india')] ?? '');
        }

        $event = (new Event())
            ->setEventId($context->eventID)
            ->setEventName($context->eventName)
            ->setEventTime($context->eventTime)
            ->setUserData($userData);

        // set custom contents if any
        if ($context->contents !== null) {
            $data = (new CustomData())
                ->setContents($context->contents)
                ->setCurrency($context->currency)
                ->setValue($context->value);
            $event->setCustomData($data);
        }

        if ($context->locationURL !== null) {
            $event->setEventSourceUrl($context->locationURL);
        }
        array_push($this->events, $event);
        return $this;
    }

    public function DumpQueue(): EventResponse
    {
        if (count($this->events) === 0) {
            throw new Exception('Event queue is empty');
        }
        $request = (new EventRequest($this->apiConfig['PixelID']['Value']))->setEvents($this->events);
        $response = $request->execute();
        $this->ClearQueue();
        return $response;
    }

    public function ClearQueue(): void
    {
        $this->events = [];
    }

    protected function APIInstance(): Api
    {
        return Api::init(null, null, $this->apiConfig['AccessToken']['Value']);
    }

    public static function GetFBCookies()
    {
        if (Utilities::IsCLI()) {
            [
                'fbc' => '',
                'fbp' => ''
            ];
        }
        return [
            'fbc' => $_COOKIE['_fbc'] ?? '',
            'fbp' => $_COOKIE['_fbp'] ?? ''
        ];
    }

    public static function ContentProduct(string $productName, int $pid, float $value, int $qty): Content
    {
        return (new Content())
            ->setProductId($pid)
            ->setTitle($productName)
            ->setItemPrice($value)
            ->setQuantity($qty);
    }

    public static function AdHocArray($firstName = '', $lastName = '', $email = '', $phone = '', $zipCode = '', $city = '', $state = '', $country = '')
    {
        return array(
            'firstName' => $firstName,
            'lastName' => $lastName,
            'email' => $email,
            'phoneNumber' => $phone,
            'zipCode' => $zipCode,
            'city' => $city,
            'state' => $state,
            'country' => $country
        );
    }
}
