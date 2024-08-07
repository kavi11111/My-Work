<?php

namespace Lib\GoogleAnalytics {

    use Exception;
    use Lib\Utilities;
    use Lib\Properties;

    class GoogleAnalytics
    {
        private ?array $GACredential;
        private ?string $apiUrl;

        public function __construct()
        {
            try {
                $this->GACredential = Properties::GetClassBundle('GACredential');
                if (!isset($this->GACredential)) {
                    throw new Exception('GACredential is empty');
                }
            } catch (\Exception $e) {
                throw $e;
            }
            $this->GACredential = $this->GACredential['GACredential'];
            $this->apiUrl = $this->GACredential['ApiUrl']['Value'] . "?api_secret=" . $this->GACredential['ApiSecret']['Value'] .
                "&measurement_id=" . $this->GACredential['MeasurementId']['Value'];
        }

        public static function GetGAClientId(): ?string
        {
            if (Utilities::IsCLI() === true) {
                return null;
            }
            $gtag = array_reverse(explode('.', $_COOKIE['_ga']));
            return implode('.', [$gtag[1], $gtag[0]]);
        }

        public function SendEvent(array $eventData)
        {
            return \Lib\Http\Request::Post(
                $this->apiUrl,
                $this->GetPayloadData($eventData),
                $this->GetHeaders(),
                true
            );
        }

        private function GetPayloadData($Item)
        {
            $data = json_decode($Item['Data'], true);
            array_walk_recursive($data, function (&$value, $key) {
                $value = str_replace('&', 'and', $value);
            });

            return [
                'client_id' => $Item['UserId'],
                'timestamp_micros' => strtotime($Item['LogTime']) * 1000000,
                'events' => [
                    [
                        'name' => $Item['Event'],
                        'params' => $data
                    ]
                ]
            ];
        }

        private function GetHeaders()
        {
            return [
                'Content-Type: application/json'
            ];
        }
    }
}
