<?php
class ApiClient {
    private $serviceUrls = [];

    public function setServiceUrl($serviceName, $url) {
        $this->serviceUrls[$serviceName] = rtrim($url, '/');
    }

    private function request($method, $service, $path, $body = null, $headers = []) {
        if (!isset($this->serviceUrls[$service])) {
            throw new Exception("Service [$service] chưa được cấu hình!");
        }

        $url = rtrim($this->serviceUrls[$service], '/') . '/' . ltrim($path, '/');

        if ($method === 'GET' && !empty($_SERVER['QUERY_STRING'])) {
            $url .= '?' . $_SERVER['QUERY_STRING'];
        }

        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $method);

        if ($body !== null) {
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($body));
            if (!array_filter($headers, fn($h) => stripos($h, 'Content-Type:') !== false)) {
                $headers[] = 'Content-Type: application/json';
            }
        }

        if (!empty($headers)) {
            curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        }

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);

        if ($response === false) {
            $error = curl_error($ch);
            curl_close($ch);
            throw new Exception("Request error: $error");
        }

        curl_close($ch);

        return [
            'status_code' => $httpCode,
            'raw_response' => $response,
            'decoded_response' => json_decode($response, true)
        ];
    }
    
    public function get($service, $path, $headers = []) {
        return $this->request('GET', $service, $path, null, $headers);
    }

    public function post($service, $path, $body, $headers = []) {
        return $this->request('POST', $service, $path, $body, $headers);
    }

    public function put($service, $path, $body, $headers = []) {
        return $this->request('PUT', $service, $path, $body, $headers);
    }

    public function delete($service, $path, $headers = []) {
        return $this->request('DELETE', $service, $path, null, $headers);
    }
}