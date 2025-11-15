<?php
/**
 * ============================================
 * shared/classes/ApiClient.php
 * FIXED VERSION - With proper token parameter support
 * ============================================
 */

class ApiClient {
    private $serviceUrls = [];

    public function setServiceUrl($serviceName, $url) {
        $this->serviceUrls[$serviceName] = rtrim($url, '/');
    }

    private function request($method, $service, $path, $body = null, $headers = [], $token = null) {
        if (!isset($this->serviceUrls[$service])) {
            throw new Exception("Service [$service] not configured!");
        }

        $url = rtrim($this->serviceUrls[$service], '/') . '/' . ltrim($path, '/');

        if ($method === 'GET' && !empty($_SERVER['QUERY_STRING'])) {
            $url .= '?' . $_SERVER['QUERY_STRING'];
        }

        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $method);

        // Add body if present
        if ($body !== null) {
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($body));
            if (!array_filter($headers, fn($h) => stripos($h, 'Content-Type:') !== false)) {
                $headers[] = 'Content-Type: application/json';
            }
        }

        // CRITICAL FIX: Add Authorization header if token is provided
        if ($token !== null && !empty($token)) {
            // Check if Authorization header already exists
            $hasAuth = false;
            foreach ($headers as $header) {
                if (stripos($header, 'Authorization:') !== false) {
                    $hasAuth = true;
                    break;
                }
            }
            
            // Add Authorization header if not present
            if (!$hasAuth) {
                $headers[] = 'Authorization: Bearer ' . $token;
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
    
    /**
     * GET request
     * @param string $service Service name
     * @param string $path Endpoint path
     * @param array $headers Additional headers
     * @param string|null $token JWT token (optional)
     * @return array Response with status_code, raw_response, decoded_response
     */
    public function get($service, $path, $headers = [], $token = null) {
        return $this->request('GET', $service, $path, null, $headers, $token);
    }

    /**
     * POST request
     * @param string $service Service name
     * @param string $path Endpoint path
     * @param mixed $body Request body
     * @param array $headers Additional headers
     * @param string|null $token JWT token (optional)
     * @return array Response with status_code, raw_response, decoded_response
     */
    public function post($service, $path, $body, $headers = [], $token = null) {
        return $this->request('POST', $service, $path, $body, $headers, $token);
    }

    /**
     * PUT request
     * @param string $service Service name
     * @param string $path Endpoint path
     * @param mixed $body Request body
     * @param array $headers Additional headers
     * @param string|null $token JWT token (optional)
     * @return array Response with status_code, raw_response, decoded_response
     */
    public function put($service, $path, $body, $headers = [], $token = null) {
        return $this->request('PUT', $service, $path, $body, $headers, $token);
    }

    /**
     * DELETE request
     * @param string $service Service name
     * @param string $path Endpoint path
     * @param array $headers Additional headers
     * @param string|null $token JWT token (optional)
     * @return array Response with status_code, raw_response, decoded_response
     */
    public function delete($service, $path, $headers = [], $token = null) {
        return $this->request('DELETE', $service, $path, null, $headers, $token);
    }
}