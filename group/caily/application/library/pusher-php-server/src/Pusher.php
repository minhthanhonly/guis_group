<?php

namespace Pusher;

class Pusher
{
    private $app_id;
    private $key;
    private $secret;
    private $options;

    public function __construct($key, $secret, $app_id, $options = array())
    {
        $this->key = $key;
        $this->secret = $secret;
        $this->app_id = $app_id;
        $this->options = $options;
    }

    public function trigger($channels, $event, $data, $socket_id = null, $info = null)
    {
        if (is_string($channels)) {
            $channels = array($channels);
        }

        $post_data = array(
            'name' => $event,
            'data' => $data,
            'channels' => $channels
        );

        if ($socket_id) {
            $post_data['socket_id'] = $socket_id;
        }

        $url = $this->get_url();
        $response = $this->make_request('POST', $url, $post_data);

        return $response;
    }

    public function socket_auth($channel_name, $socket_id, $custom_data = null)
    {
        $signature = $this->sign($channel_name . ':' . $socket_id);

        $response = array(
            'auth' => $this->key . ':' . $signature
        );

        if ($custom_data) {
            $response['channel_data'] = $custom_data;
        }

        return json_encode($response);
    }

    private function sign($string)
    {
        return hash_hmac('sha256', $string, $this->secret, false);
    }

    private function get_url()
    {
        $cluster = isset($this->options['cluster']) ? $this->options['cluster'] : 'mt1';
        $scheme = isset($this->options['useTLS']) && $this->options['useTLS'] ? 'https' : 'http';
        return $scheme . '://api-' . $cluster . '.pusherapp.com/apps/' . $this->app_id . '/events';
    }

    private function make_request($method, $url, $params = array())
    {
        $ch = curl_init();

        if ($method === 'POST') {
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($params));
        }

        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 30);
        curl_setopt($ch, CURLOPT_HTTPHEADER, array(
            'Content-Type: application/x-www-form-urlencoded',
            'X-Pusher-Library: pusher-php-server/1.0'
        ));

        $response = curl_exec($ch);
        $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($http_code !== 200) {
            throw new \Exception('Pusher API request failed with HTTP code: ' . $http_code);
        }

        return json_decode($response, true);
    }
} 