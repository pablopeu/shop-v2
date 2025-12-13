<?php
/**
 * Mercadopago Integration
 * Simple wrapper for Mercadopago API without requiring composer
 */

class MercadoPago {
    private $access_token;
    private $public_key;
    private $sandbox;
    private $base_url;

    public function __construct($access_token, $sandbox = true, $public_key = null) {
        $this->access_token = $access_token;
        $this->public_key = $public_key;
        $this->sandbox = $sandbox;
        $this->base_url = 'https://api.mercadopago.com';
    }

    /**
     * Create a payment preference
     */
    public function createPreference($data) {
        $url = $this->base_url . '/checkout/preferences';

        $headers = [
            'Authorization: Bearer ' . $this->access_token,
            'Content-Type: application/json'
        ];

        // Debug: Log the JSON being sent to MercadoPago
        $json_payload = json_encode($data);
        error_log("MP createPreference - JSON payload: " . $json_payload);

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $json_payload);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);

        $response = curl_exec($ch);
        $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);

        if (curl_errno($ch)) {
            $error = curl_error($ch);
            curl_close($ch);
            throw new Exception('Curl error: ' . $error);
        }

        curl_close($ch);

        $result = json_decode($response, true);

        if ($http_code !== 201 && $http_code !== 200) {
            // Extract detailed error information
            $error_msg = 'Mercadopago API error (HTTP ' . $http_code . '): ';

            if (isset($result['message'])) {
                $error_msg .= $result['message'];
            } elseif (isset($result['error'])) {
                $error_msg .= $result['error'];
            } else {
                $error_msg .= 'Unknown error';
            }

            // Add cause details if available
            if (isset($result['cause']) && is_array($result['cause'])) {
                foreach ($result['cause'] as $cause) {
                    if (isset($cause['code']) && isset($cause['description'])) {
                        $error_msg .= ' [' . $cause['code'] . ': ' . $cause['description'] . ']';
                    }
                }
            }

            // Log full response for debugging
            error_log("Mercadopago createPreference error: " . json_encode($result));

            throw new Exception($error_msg);
        }

        return $result;
    }

    /**
     * Create a payment
     */
    public function createPayment($data) {
        $url = $this->base_url . '/v1/payments';

        $headers = [
            'Authorization: Bearer ' . $this->access_token,
            'Content-Type: application/json',
            'X-Idempotency-Key: ' . uniqid('payment_', true)
        ];

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);

        $response = curl_exec($ch);
        $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);

        if (curl_errno($ch)) {
            $error = curl_error($ch);
            curl_close($ch);
            throw new Exception('Curl error: ' . $error);
        }

        curl_close($ch);

        $result = json_decode($response, true);

        if ($http_code !== 201 && $http_code !== 200) {
            // Extract detailed error information
            $error_msg = 'Mercadopago API error (HTTP ' . $http_code . '): ';

            if (isset($result['message'])) {
                $error_msg .= $result['message'];
            } elseif (isset($result['error'])) {
                $error_msg .= $result['error'];
            } else {
                $error_msg .= 'Unknown error';
            }

            // Add cause details if available
            if (isset($result['cause']) && is_array($result['cause'])) {
                foreach ($result['cause'] as $cause) {
                    if (isset($cause['code']) && isset($cause['description'])) {
                        $error_msg .= ' [' . $cause['code'] . ': ' . $cause['description'] . ']';
                    }
                }
            }

            // Log full response for debugging
            error_log("Mercadopago createPayment error: " . json_encode($result));

            throw new Exception($error_msg);
        }

        return $result;
    }

    /**
     * Create a card token using public key
     */
    public function createCardToken($card_data) {
        if (empty($this->public_key)) {
            throw new Exception('Public key is required to create card tokens');
        }

        $url = $this->base_url . '/v1/card_tokens?public_key=' . $this->public_key;

        $headers = [
            'Content-Type: application/json'
        ];

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($card_data));
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);

        $response = curl_exec($ch);
        $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);

        if (curl_errno($ch)) {
            $error = curl_error($ch);
            curl_close($ch);
            throw new Exception('Curl error: ' . $error);
        }

        curl_close($ch);

        $result = json_decode($response, true);

        if ($http_code !== 201 && $http_code !== 200) {
            // Extract detailed error information
            $error_msg = 'Mercadopago API error (HTTP ' . $http_code . '): ';

            if (isset($result['message'])) {
                $error_msg .= $result['message'];
            } elseif (isset($result['error'])) {
                $error_msg .= $result['error'];
            } else {
                $error_msg .= 'Unknown error';
            }

            // Add cause details if available
            if (isset($result['cause']) && is_array($result['cause'])) {
                foreach ($result['cause'] as $cause) {
                    if (isset($cause['code']) && isset($cause['description'])) {
                        $error_msg .= ' [' . $cause['code'] . ': ' . $cause['description'] . ']';
                    }
                }
            }

            // Log full response for debugging
            error_log("Mercadopago createCardToken error: " . json_encode($result));

            throw new Exception($error_msg);
        }

        return $result;
    }

    /**
     * Get payment information
     */
    public function getPayment($payment_id) {
        $url = $this->base_url . '/v1/payments/' . $payment_id;

        $headers = [
            'Authorization: Bearer ' . $this->access_token,
            'Content-Type: application/json'
        ];

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);

        $response = curl_exec($ch);
        $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);

        if (curl_errno($ch)) {
            $error = curl_error($ch);
            curl_close($ch);
            throw new Exception('Curl error: ' . $error);
        }

        curl_close($ch);

        $result = json_decode($response, true);

        if ($http_code !== 200) {
            throw new Exception('Mercadopago API error: ' . ($result['message'] ?? 'Unknown error'));
        }

        return $result;
    }

    /**
     * Validate webhook signature (x-signature header)
     */
    public static function validateWebhookSignature($request_data, $signature_header, $secret) {
        // Extract signature parts from header
        // Format: ts=timestamp,v1=hash
        $parts = [];
        foreach (explode(',', $signature_header) as $part) {
            list($key, $value) = explode('=', $part, 2);
            $parts[$key] = $value;
        }

        if (!isset($parts['ts']) || !isset($parts['v1'])) {
            return false;
        }

        $timestamp = $parts['ts'];
        $received_hash = $parts['v1'];

        // Construct the manifest
        $manifest = "id:{$request_data['id']};request-id:{$request_data['request_id']};ts:{$timestamp};";

        // Calculate expected hash
        $expected_hash = hash_hmac('sha256', $manifest, $secret);

        // Compare hashes
        return hash_equals($expected_hash, $received_hash);
    }

    /**
     * Get init point URL (checkout URL)
     */
    public function getInitPoint($preference) {
        if ($this->sandbox) {
            return $preference['sandbox_init_point'] ?? $preference['init_point'];
        }
        return $preference['init_point'];
    }
}
