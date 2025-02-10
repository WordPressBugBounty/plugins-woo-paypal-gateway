<?php

class ApplePayConfiguration
{
    private static ?ApplePayConfiguration $instance = null;
    private string $host;
    private string $domainValidationFileUrl = 'https://www.paypalobjects.com/.well-known/apple-developer-domain-association';

    private function __construct()
    {
        $this->host = $this->is_sandbox() ? 'api.sandbox.paypal.com' : 'api.paypal.com';

        add_action('wp_ajax_list_domains', [$this, 'list_domains']);
        add_action('wp_ajax_register_domain', [$this, 'register_domain_ajax']);
        add_action('wp_ajax_remove_domain', [$this, 'remove_domain_ajax']);
    }

    public static function instance(): self
    {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function get_api_headers(): array
    {
        return [
            'Content-Type' => 'application/json',
            'Authorization' => '', // Add actual token retrieval logic
            'prefer' => 'return=representation',
            'Paypal-Auth-Assertion' => $this->get_paypal_auth_assertion(),
        ];
    }

    public function list_domains(): void
    {
        $url = sprintf('https://%s/v1/customer/wallet-domains', $this->host);
        $params = [
            'provider_type' => 'APPLE_PAY', 
            'page_size' => 10, 
            'page' => 1
        ];
        $url = add_query_arg($params, $url);

        $response = $this->request($url, [
            'method' => 'GET',
            'headers' => $this->get_api_headers(),
        ]);

        wp_send_json($response);
    }

    public function register_domain(string $domain): array
    {
        if (!filter_var($domain, FILTER_VALIDATE_DOMAIN)) {
            throw new InvalidArgumentException(__('Invalid domain name.', 'woo-paypal-gateway'));
        }

        $url = sprintf('https://%s/v1/customer/wallet-domains', $this->host);
        $data = [
            'provider_type' => 'APPLE_PAY',
            'domain' => ['name' => $domain],
        ];

        $response = $this->request($url, [
            'method' => 'POST',
            'headers' => $this->get_api_headers(),
            'body' => wp_json_encode($data),
        ]);

        if (isset($response['domain'])) {
            return [
                'status' => true,
                'message' => __('Domain registered successfully.', 'woo-paypal-gateway'),
                'domain' => $response['domain'],
            ];
        }

        throw new RuntimeException(__('Domain registration failed.', 'woo-paypal-gateway'));
    }

    public function remove_domain(string $domain): array
    {
        $url = sprintf('https://%s/v1/customer/unregister-wallet-domain', $this->host);
        $data = [
            'provider_type' => 'APPLE_PAY',
            'domain' => ['name' => $domain],
            'reason' => 'Requested by administrator',
        ];

        $response = $this->request($url, [
            'method' => 'POST',
            'headers' => $this->get_api_headers(),
            'body' => wp_json_encode($data),
        ]);

        if (isset($response['domain'])) {
            return [
                'status' => true,
                'message' => __('Domain removed successfully.', 'woo-paypal-gateway'),
            ];
        }

        throw new RuntimeException(__('Domain removal failed.', 'woo-paypal-gateway'));
    }

    public function register_domain_ajax(): void
    {
        try {
            $domain = sanitize_text_field($_POST['domain'] ?? parse_url(get_site_url(), PHP_URL_HOST));
            $response = $this->register_domain($domain);
            wp_send_json($response);
        } catch (Exception $e) {
            wp_send_json(['status' => false, 'message' => $e->getMessage()]);
        }
    }

    public function remove_domain_ajax(): void
    {
        try {
            $domain = sanitize_text_field($_POST['domain'] ?? parse_url(get_site_url(), PHP_URL_HOST));
            $response = $this->remove_domain($domain);
            wp_send_json($response);
        } catch (Exception $e) {
            wp_send_json(['status' => false, 'message' => $e->getMessage()]);
        }
    }

    public function add_domain_validation_file(string $local_path): void
    {
        if (!is_dir(dirname($local_path))) {
            wp_mkdir_p(dirname($local_path));
        }

        $content = $this->fetch_domain_validation_file();
        if (!file_put_contents($local_path, $content)) {
            throw new RuntimeException(__('Failed to write domain validation file.', 'woo-paypal-gateway'));
        }
    }

    private function fetch_domain_validation_file(): string
    {
        $response = wp_remote_get($this->domainValidationFileUrl);
        if (is_wp_error($response)) {
            throw new RuntimeException(__('Failed to fetch domain validation file.', 'woo-paypal-gateway'));
        }

        return wp_remote_retrieve_body($response);
    }

    private function is_sandbox(): bool
    {
        // Add logic to determine sandbox mode
        return true;
    }

    private function get_paypal_auth_assertion(): string
    {
        // Add logic to generate PayPal Auth Assertion
        return 'auth_assertion_token';
    }

    private function request(string $url, array $args): array
    {
        $response = wp_remote_request($url, $args);
        if (is_wp_error($response)) {
            throw new RuntimeException($response->get_error_message());
        }

        return json_decode(wp_remote_retrieve_body($response), true);
    }
}
