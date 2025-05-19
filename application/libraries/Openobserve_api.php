<?php 
defined('BASEPATH') OR exit('No direct script access allowed');

class Openobserve_api 
{
    const API_URL = 'https://openobserve.qsec.openobserve.ai/kibana/api/_meta/_search?type=logs';

    private $ch;
    private $meta_org_username;
    private $meta_org_password;

    function __construct()
    {
        $CI =& get_instance();

        $this->meta_org_username    = $CI->config->item('meta_org_username');
        $this->meta_org_password    = $CI->config->item('meta_org_password');
    }

    public function get_total_ingestion($client, $start_time, $end_time)
    {
        $header_fields = array(
            'Content-Type: application/json',
            'Accept: application/json',
            'Authorization: Basic '.base64_encode($this->meta_org_username.':'.$this->meta_org_password)
        );

        $query = new stdClass();
        $query->sql         = "SELECT sum(size) as total_ingestion FROM usage WHERE event IN ('Ingestion') AND org_id = '$client'";
        $query->start_time  = $start_time * 1000000;
        $query->end_time    = $end_time * 1000000;
        // $query->from        = ;
        // $query->size        = ;

        $post_fields = new stdClass();
        $post_fields->query = $query;

        $response = $this->call_api('POST', self::API_URL, $header_fields, json_encode($post_fields));

        if ($response['result'] !== FALSE)
        {
            if ($response['http_code'] === 200)
            {
                return array(
                    'success'   => TRUE,
                    'response'  => $response['result']
                );
            }
            else
            {
                return array(
                    'success'   => FALSE,
                    'response'  => $response['result']
                );
            }
        }
        else
        {
            return array(
                'success'   => FALSE,
                'response'  => array(
                    'status'    => 'cURL returned false',
                    'message'   => 'errno = '.$response['errno'].', error = '.$response['error']
                )
            );
        }
    }

    public function get_ingestion_histogram($client, $start_time, $end_time, $interval = '24 hours')
    {
        $header_fields = array(
            'Content-Type: application/json',
            'Accept: application/json',
            'Authorization: Basic '.base64_encode($this->meta_org_username.':'.$this->meta_org_password)
        );

        $query = new stdClass();
        $query->sql         = "SELECT histogram(_timestamp, '$interval') as x_axis, sum(size) as y_axis FROM usage WHERE event IN ('Ingestion') AND org_id = '$client' GROUP BY x_axis ORDER BY x_axis ASC";
        $query->start_time  = $start_time * 1000000;
        $query->end_time    = $end_time * 1000000;

        if ($interval === '30 minutes')
        {
            $query->from    = 0;
            $query->size    = 1500;
        }
        else if ($interval === '15 minutes')
        {
            $query->from    = 0;
            $query->size    = 3000;
        }

        $post_fields = new stdClass();
        $post_fields->query = $query;

        $response = $this->call_api('POST', self::API_URL, $header_fields, json_encode($post_fields));

        if ($response['result'] !== FALSE)
        {
            if ($response['http_code'] === 200)
            {
                return array(
                    'success'   => TRUE,
                    'response'  => $response['result']
                );
            }
            else
            {
                return array(
                    'success'   => FALSE,
                    'response'  => $response['result']
                );
            }
        }
        else
        {
            return array(
                'success'   => FALSE,
                'response'  => array(
                    'status'    => 'cURL returned false',
                    'message'   => 'errno = '.$response['errno'].', error = '.$response['error']
                )
            );
        }
    }

    public function get_org_ids($start_time, $end_time)
    {
        $header_fields = array(
            'Content-Type: application/json',
            'Accept: application/json',
            'Authorization: Basic '.base64_encode($this->meta_org_username.':'.$this->meta_org_password)
        );

        $query = new stdClass();
        $query->sql         = "SELECT distinct(org_id) FROM usage ORDER BY org_id ASC";
        $query->start_time  = $start_time * 1000000;
        $query->end_time    = $end_time * 1000000;
        // $query->from        = ;
        // $query->size        = ;

        $post_fields = new stdClass();
        $post_fields->query = $query;

        $response = $this->call_api('POST', self::API_URL, $header_fields, json_encode($post_fields));

        if ($response['result'] !== FALSE)
        {
            if ($response['http_code'] === 200)
            {
                return array(
                    'success'   => TRUE,
                    'response'  => $response['result']
                );
            }
            else
            {
                return array(
                    'success'   => FALSE,
                    'response'  => $response['result']
                );
            }
        }
        else
        {
            return array(
                'success'   => FALSE,
                'response'  => array(
                    'status'    => 'cURL returned false',
                    'message'   => 'errno = '.$response['errno'].', error = '.$response['error']
                )
            );
        }
    }

    private function call_api($method, $url, $header_fields, $post_fields = NULL)
    {
        $this->ch = curl_init();

        switch ($method)
        {
            case 'POST':
                curl_setopt($this->ch, CURLOPT_POST, true);

                if (isset($post_fields))
                {
                    curl_setopt($this->ch, CURLOPT_POSTFIELDS, $post_fields);
                }

                break;
            case 'PUT':
                curl_setopt($this->ch, CURLOPT_CUSTOMREQUEST, 'PUT');

                if (isset($post_fields))
                {
                    curl_setopt($this->ch, CURLOPT_POSTFIELDS, $post_fields);
                }

                break;
            case 'DELETE':
                curl_setopt($this->ch, CURLOPT_CUSTOMREQUEST, 'DELETE');
                break;
        }

        if (is_array($header_fields))
        {
            curl_setopt($this->ch, CURLOPT_HTTPHEADER, $header_fields);
        }

        curl_setopt($this->ch, CURLOPT_URL, $url);
        curl_setopt($this->ch, CURLOPT_RETURNTRANSFER, true);
        //curl_setopt($this->ch, CURLOPT_SSL_VERIFYHOST, false);
        //curl_setopt($this->ch, CURLOPT_SSL_VERIFYPEER, false);

        curl_setopt($this->ch, CURLOPT_CONNECTTIMEOUT, 5);
        //curl_setopt($this->ch, CURLOPT_TIMEOUT, 10);

        if (($response['result'] = curl_exec($this->ch)) !== FALSE)
        {
            if (($response['http_code'] = curl_getinfo($this->ch, CURLINFO_HTTP_CODE)) === 200)
            {
                // Make sure the size of the response is non-zero prior to json_decode()
                if (curl_getinfo($this->ch, CURLINFO_SIZE_DOWNLOAD_T))
                {
                    $response['result'] = json_decode($response['result'], TRUE);
                }
            }
        }
        else
        {
            $response['errno'] 	= curl_errno($this->ch);
            $response['error'] 	= curl_error($this->ch);
        }

        curl_close($this->ch);

        return $response;
    }
}