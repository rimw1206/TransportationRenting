<?php
/**
 * API Client for inter-service communication
 */
class ApiClient
{
    private $serviceUrls = [];
    private $timeout = 5;
    private $maxRetries = 3;
    
    /**
     * Set service URL
     */
    public function setServiceUrl($serviceName, $url)
    {
        $this->serviceUrls[$serviceName] = rtrim($url, '/');
    }
    
    /**
     * Set timeout
     */
    public function setTimeout($timeout)
    {
        $this->timeout = $timeout;
    }
    
    /**
     * GET request
     */
    public function get($service, $path, $headers = [])
    {
        return $this->request($service, $path, 'GET', null, $headers);
    }
    
    /**
     * POST request
     */
    public function post($service, $path, $data = null, $headers = [])
    {
        return $this->request($service, $path, 'POST', $data, $headers);
    }
    
    /**
     * PUT request
     */
    public function put($service, $path, $data = null, $headers = [])
    {
        return $this->request($service, $path, 'PUT', $data, $headers);
    }
    
    /**
     * PATCH request
     */
    public function patch($service, $path, $data = null, $headers = [])
    {
        return $this->request($service, $path, 'PATCH', $data, $headers);
    }
    
    /**
     * DELETE request
     */
    public function delete($service, $path, $headers = [])
    {
        return $this->request($service, $path, 'DELETE', null, $headers);
    }
    
    /**
     * Make HTTP request with retry logic
     */
    public function request($service, $path, $method, $data = null, $headers = [])
    {
        $serviceUrl = $this->serviceUrls[$service] ?? null;
        
        if (!$serviceUrl) {
            return [
                'success' => false,
                'message' => "Service URL not configured for: $service",
                'status_code' => 500,
                'raw_response' => json_encode([
                    'success' => false,
                    'message' => "Service URL not configured for: $service"
                ])
            ];
        }
        
        $url = $serviceUrl . $path;
        $attempts = 0;
        
        while ($attempts < $this->maxRetries) {
            $attempts++;
            
            try {
                $result = $this->makeRequest($url, $method, $data, $headers);
                
                // Success or non-retryable error
                if ($result['status_code'] < 500) {
                    return $result;
                }
                
                // Retry on 5xx errors
                if ($attempts < $this->maxRetries) {
                    usleep(100000 * $attempts); // Exponential backoff: 100ms, 200ms, 300ms
                    continue;
                }
                
                return $result;
                
            } catch (Exception $e) {
                error_log("API Client error (attempt $attempts): " . $e->getMessage());
                
                if ($attempts >= $this->maxRetries) {
                    return [
                        'success' => false,
                        'message' => 'Service temporarily unavailable',
                        'status_code' => 503,
                        'raw_response' => json_encode([
                            'success' => false,
                            'message' => 'Service temporarily unavailable'
                        ])
                    ];
                }
                
                usleep(100000 * $attempts);
            }
        }
        
        return [
            'success' => false,
            'message' => 'Max retries exceeded',
            'status_code' => 503,
            'raw_response' => json_encode([
                'success' => false,
                'message' => 'Service temporarily unavailable after retries'
            ])
        ];
    }
    
    /**
     * Make actual HTTP request using cURL
     */
    private function makeRequest($url, $method, $data, $headers)
    {
        $ch = curl_init($url);
        
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, $this->timeout);
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $method);
        
        // Set headers
        $curlHeaders = ['Content-Type: application/json'];
        foreach ($headers as $header) {
            $curlHeaders[] = $header;
        }
        curl_setopt($ch, CURLOPT_HTTPHEADER, $curlHeaders);
        
        // Set body for POST/PUT/PATCH
        if (in_array($method, ['POST', 'PUT', 'PATCH']) && $data !== null) {
            $jsonData = is_string($data) ? $data : json_encode($data);
            curl_setopt($ch, CURLOPT_POSTFIELDS, $jsonData);
        }
        
        $response = curl_exec($ch);
        $statusCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlError = curl_error($ch);
        curl_close($ch);
        
        if ($curlError) {
            throw new Exception("cURL error: $curlError");
        }
        
        if ($response === false) {
            throw new Exception("Empty response from service");
        }
        
        // Try to decode JSON response
        $decoded = json_decode($response, true);
        
        return [
            'success' => ($statusCode >= 200 && $statusCode < 300),
            'status_code' => $statusCode,
            'data' => $decoded,
            'raw_response' => $response
        ];
    }
    
    /**
     * Health check for a service
     */
    public function healthCheck($service)
    {
        $serviceUrl = $this->serviceUrls[$service] ?? null;
        
        if (!$serviceUrl) {
            return false;
        }
        
        $ch = curl_init($serviceUrl . '/health');
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 2);
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 2);
        
        $response = curl_exec($ch);
        $statusCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        
        return ($statusCode === 200);
    }
}