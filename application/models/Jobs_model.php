<?php if (!defined('BASEPATH')) exit('No direct script access allowed');

class Jobs_model extends CI_Model {

	private $redis_timeout;

	public function __construct()
	{
		parent::__construct();

		$this->redis_timeout    = 3500;
	}

	function get_active_clients()
	{
		$this->db->db_select('quadrant_central');
		
		$this->db->select('id, client, seed_name, database, multi_client_enabled, multi_client_link_id, redis_host, redis_port, redis_password');
		$this->db->where('is_active', 1);
			
		$query = $this->db->get('clients');
	
		if ($query->num_rows() > 0) 
		{
			foreach($query->result() as $row) 
			{	
				$data[] = $row;
			}
			
			return $data;
			
		} 
		else 
		{
			return NULL;
		}
	}

	public function get_email_distribution_list($client_id, $multi_console, $multi_link_id)
	{
		if ($multi_console)
		{
			$ids = $this->get_linked_client_ids(TRUE, $client_id, $multi_link_id);
		}

		$authdb = $this->load->database('authentication',TRUE);

			$authdb->select('user_profiles.first_name, user_profiles.last_name, user_profiles.office_country, user_profiles.office_phone, user_profiles.office_phone_ext');	
			$authdb->select('users.id AS user_id, users.email, users.mobile, users.country, users.code, users.client_id');	
			$authdb->select('email_distribution_list.priority, email_distribution_list.alerts, email_distribution_list.maintenance, email_distribution_list.reports_24_hour, email_distribution_list.reports_executive');
			$authdb->select('m.calling_code AS country_mobile');	
			$authdb->select('o.calling_code AS country_office');
			$authdb->select('user_groups.security_group_id');

			$authdb->join('users', 'users.id = user_profiles.user_id','inner');
			$authdb->join('user_groups', 'user_groups.user_id = users.id','inner');
			$authdb->join('email_distribution_list', 'email_distribution_list.user_id = users.id','inner');
			$authdb->join('country_codes m', 'm.iso2 = users.country','left');
			$authdb->join('country_codes o', 'o.iso2 = user_profiles.office_country','left');

			if ($multi_console)
			{
				$authdb->select('clients.client');
				$authdb->join('clients', 'clients.id = users.client_id','inner');

				$authdb->where_in('users.client_id', $ids);
				//$authdb->where('IF(`users`.`client_id` = '.intval($this->session->userdata('active_client_id')).', `user_profiles`.`on_email_distribution_list` = 1, `user_clients`.`on_email_distribution_list` = 1)',NULL,FALSE);
			}
			else
			{
				$authdb->where('users.client_id', intval($client_id));
				//$authdb->where('user_profiles.on_email_distribution_list', 1);
			}

			$authdb->where('email_distribution_list.client_id', intval($client_id));
			$authdb->where('users.banned', 0);
			$authdb->where('user_groups.application_id', 1);

			$authdb->order_by('email_distribution_list.priority', 'ASC');

			$query = $authdb->get('user_profiles');

		$authdb->close();

		if ($query->num_rows() > 0) 
		{
			foreach($query->result() as $row) 
			{	
				$data[] = $row;
			}
			$return_array = array(
				'count' => $query->num_rows(),
				'data'  => $data
			);
			return $return_array;		
		} 
		$return_array = array(
			'count' => 0,
			'data'  => NULL
		);
		return $return_array;

	}

	public function get_linked_client_ids($include_active_client, $client_id, $multi_link_id)	
	{
		$authdb = $this->load->database('authentication',TRUE);
			$authdb->select('client_companies.client_id AS id');
			$authdb->where('link_id', $multi_link_id);
			$query = $authdb->get('client_companies');
		$authdb->close();

		if ($query->num_rows() > 0) 
		{
			$return_ids = array();

			foreach($query->result() as $row) 
			{	
				if ($include_active_client)
				{
					array_push($return_ids, intval($row->id));
				}
				else
				{
					if (intval($row->id) !== intval($client_id))
					{
						array_push($return_ids, intval($row->id));
					}
				}			
			}			
			return $return_ids;

		}
		return NULL;
	}

	public function get_log_ingestion_notification($client)
	{
		$redis          = new Redis();
		$client_key     = $client->seed_name.'_configurations';
		$notification   = NULL;

		$redis->connect($client->redis_host, $client->redis_port, $this->redis_timeout);
		$redis->auth($client->redis_password);

		try
		{
			if ($redis->exists($client_key))
			{
				$notification = $redis->hGet($client_key, 'log_ingestion_notification');
			}
		}
		catch (Exception $e)
		{
			# Write To Logs
			$msg            = str_replace('.', '', $e->getMessage());
			$log_message    = '[REDIS-EXCEPTION] message: '.$msg.', this took place in Jobs_model.get_log_ingestion_notification() for client: '.$client->client.'.';
			$this->utility->write_log_entry('info', $log_message);

			$redis->close();
			return NULL;
		}

		$redis->close();

		if (!empty($notification))
		{
			return json_decode($notification);
		}

		return NULL;
	}

	public function get_month_full_name($month)
	{
		$month_full_names = ['January', 'February', 'March', 'April', 'May', 'June', 'July', 'August', 'September', 'October', 'November', 'December'];

		return $month_full_names[$month - 1];
	}

	public function get_days_in_month($month)
	{
		if ($month === 2)  // Feb
		{
			return date('L') === '1' ? 29 : 28;  // Check if leap year
		}
		else if (in_array($month, [4, 6, 9, 11]))  // Apr, Jun, Sep, Nov
		{
			return 30;
		}
		else if (in_array($month, [1, 3, 5, 7, 8, 10, 12]))  // Jan, Mar, May, Jul, Aug, Oct, Dec
		{
			return 31;
		}
	}

	public function get_total_ingestion_and_cost($client, $start_time, $end_time, $cost_gb)
	{
		$this->load->library('openobserve_api');

		$ingestion = NULL;

		$response = $this->openobserve_api->get_total_ingestion($client, $start_time, $end_time);

		if ($response['success'])
		{
			if ($response['response']['total'] === 1)
			{
				$ingestion_mb       = $response['response']['hits'][0]['total_ingestion'];
				$ingestion_bytes    = $ingestion_mb * 1000000;
				$cost               = $ingestion_mb / 1000 * $cost_gb;
				$index              = intval(floor(log($ingestion_bytes) / log(1000)));
				$sizes              = ['B', 'KB', 'MB', 'GB', 'TB', 'PB'];

				if ($ingestion_bytes == 0)
				{
					$total = 0;
				}
				else
				{
					if ($index === 0)
					{
						$total  = $ingestion_bytes.' '.$sizes[$index];
					}
					else
					{
						$total  = number_format($ingestion_bytes / (1000 ** $index), 2).' '.$sizes[$index];
					}
				}

				$ingestion['cost']              = $cost;
				$ingestion['total']             = $total;
				$ingestion['formatted_cost']    = '$'.number_format($cost, 2);
			}
		}

		return $ingestion;
	}
}