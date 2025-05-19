<?php
defined('BASEPATH') OR exit('No direct script access allowed');

class Log_ingestion_settings extends CI_Controller 
{
	private $client;
	private $cost_gb;
	private $redis_host;
	private $redis_port;
	private $redis_password;
	private $notification_field;
	private $timezone_field;

	public function __construct()
	{
		parent::__construct();

		$this->tank_auth->check_login_status();

		if (intval($this->session->userdata('security_priority_level')) > 4)
		{
			$this->session->set_userdata('my_flash_message_type', 'error');
			$this->session->set_userdata('my_flash_message', lang('do_not_have_access_to_section'));
			redirect('console/dashboard');
		}

		$this->client           = rtrim($this->config->item('seed_name'),'-');
		$this->cost_gb          = $this->config->item('ingestion_cost_gb');
		$this->redis_host       = $this->config->item('redis_client_host');
		$this->redis_port       = $this->config->item('redis_client_port');
		$this->redis_password   = $this->config->item('redis_client_password');

		$this->notification_field   = 'log_ingestion_notification';
		$this->timezone_field       = 'log_ingestion_timezone';

		$this->load->model('account/account_model', 'account');

		$this->utility->check_redis_connection($this->redis_host, $this->redis_port, $this->redis_password, 'Preferences'); 
	}

	public function index()
	{
		if (($notification_json = client_redis_info($this->client, $this->notification_field)) !== NULL)
		{
			$notification           = json_decode($notification_json, TRUE);

			$data['monthly_budget'] = $notification['monthly_budget'];
			$data['threshold']      = $notification['threshold'];
		}
		else
		{
			$data['monthly_budget'] = '';
			$data['threshold']      = '';
		}

		if (($current_timezone = client_redis_info($this->client, $this->timezone_field)) !== NULL)
		{
			$data['set_timezone']   = $current_timezone;
		}
		else
		{
			$data['set_timezone']   = '1';  // User time zone
		}

		$user_timezone          = $this->session->userdata('user_timezone');
		$data['user_timezone']  = $user_timezone;

		$data['timezone_dropdown'] = $this->get_timezone_dropdown($user_timezone);

		# Page Views
		$this->load->view('assets/header');
		$this->load->view('account/preferences/log-ingestion-settings', $data);
		$this->load->view('assets/footer');
	}

	public function update_notification()
	{
		$notification_exists    = FALSE;
		$no_changes_made        = FALSE;

		if (($notification_json = client_redis_info($this->client, $this->notification_field)) !== NULL)
		{
			$notification           = json_decode($notification_json, TRUE);
			$notification_exists    = TRUE;

			$data['monthly_budget'] = $notification['monthly_budget'];
			$data['threshold']      = $notification['threshold'];
		}
		else
		{
			$data['monthly_budget'] = '';
			$data['threshold']      = '';
		}

		$this->form_validation->set_rules('monthly_budget', 'Monthly Budget', 'trim|required|callback_number_optional_digits');
		$this->form_validation->set_rules('threshold', 'Threshold', 'trim|required|greater_than_equal_to[1]|less_than_equal_to[100]');

		if ($this->form_validation->run()) 
		{
			$monthly_budget         = $this->input->post('monthly_budget');
			$threshold              = $this->input->post('threshold');
			$delete_notification    = $this->input->post('delete_notification') ?? '0';

			if ($notification_exists && !$delete_notification)
			{
				$old_values = array(
					'monthly_budget'    => $notification['monthly_budget'],
					'threshold'         => $notification['threshold'],
					'cost_gb'           => $notification['cost_gb']
				);

				$new_values = array(
					'monthly_budget'    => $monthly_budget,
					'threshold'         => $threshold,
					'cost_gb'           => $this->cost_gb
				);

				$diff = array_diff($new_values, $old_values);

				if (empty($diff))  // No changes made
				{
					$no_changes_made = TRUE;
				}
			}

			if (!$no_changes_made)
			{
				$redis_data = array(
					'monthly_budget'    => $monthly_budget,
					'threshold'         => $threshold,
					'cost_gb'           => $this->cost_gb
				);

				if ($this->account->update_client_configurations($this->client, $this->notification_field, json_encode($redis_data), $delete_notification))
				{
					if ($delete_notification)
					{
						$log_message    = '[Log Ingestion Notification Deleted] user: '.$this->session->userdata('username').' | for client: '.$this->session->userdata('active_client_title');
						$flash_message  = '<p>Log Ingestion Notification settings were successfully deleted.</p>';
					}
					else
					{
						if ($notification_exists)
						{
							$log_message    = '[Log Ingestion Notification Modified] user: '.$this->session->userdata('username').' | for client: '.$this->session->userdata('active_client_title');
							$flash_message  = '<p>Log Ingestion Notification settings were successfully updated.</p>';
						}
						else
						{
							$log_message    = '[Log Ingestion Notification Created] user: '.$this->session->userdata('username').' | for client: '.$this->session->userdata('active_client_title');
							$flash_message  = '<p>Log Ingestion Notification settings were successfully created.</p>';
						}
					}

					# Write To Logs
					$this->utility->write_log_entry('info', $log_message);

					# Success
					$this->session->set_userdata('my_flash_message_type', 'success');
					$this->session->set_userdata('my_flash_message', $flash_message);

					redirect('/account/preferences');
				}
				else
				{
					# Something went wrong
					$this->session->set_userdata('my_flash_message_type', 'error');
					$this->session->set_userdata('my_flash_message', '<p>Something went wrong. Please try again.</p>');
				}
			}
			else
			{
				$this->session->set_userdata('my_flash_message_type', 'success');
				$this->session->set_userdata('my_flash_message', '<p>No changes were made to the Log Ingestion Notification settings.</p>');

				redirect('/account/preferences');
			}
		}
		else
		{
			if (validation_errors()) 
			{
				$this->session->set_userdata('my_flash_message_type', 'error');
				$this->session->set_userdata('my_flash_message', validation_errors());
			}
		}

		if (($current_timezone = client_redis_info($this->client, $this->timezone_field)) !== NULL)
		{
			$data['set_timezone']   = $current_timezone;
		}
		else
		{
			$data['set_timezone']   = '1';  // User time zone
		}

		$user_timezone          = $this->session->userdata('user_timezone');
		$data['user_timezone']  = $user_timezone;

		$data['timezone_dropdown'] = $this->get_timezone_dropdown($user_timezone);

		# Page Views
		$this->load->view('assets/header');
		$this->load->view('account/preferences/log-ingestion-settings', $data);
		$this->load->view('assets/footer');
	}

	public function update_timezone()
	{
		$setting_exists     = FALSE;
		$no_changes_made    = FALSE;

		if (($current_timezone = client_redis_info($this->client, $this->timezone_field)) !== NULL)
		{
			$setting_exists         = TRUE;
			$data['set_timezone']   = $current_timezone;
		}
		else
		{
			$data['set_timezone']   = '1';  // User time zone
		}

		$this->form_validation->set_rules('timezone', 'Time Zone', 'trim|integer|required');

		if ($this->form_validation->run()) 
		{
			$timezone = $this->input->post('timezone');

			if ($setting_exists)
			{
				if ($timezone === $current_timezone)  // No changes made
				{
					$no_changes_made = TRUE;
				}
			}

			if (!$no_changes_made)
			{
				if ($this->account->update_client_configurations($this->client, $this->timezone_field, $timezone))
				{
					$this->delete_redis_data('log_ingestion_*');

					if ($setting_exists)
					{
						$log_message    = '[Log Ingestion Time Zone Modified] user: '.$this->session->userdata('username').' | for client: '.$this->session->userdata('active_client_title');
						$flash_message  = '<p>Log Ingestion Time Zone settings were successfully updated.</p>';
					}
					else
					{
						$log_message    = '[Log Ingestion Time Zone Created] user: '.$this->session->userdata('username').' | for client: '.$this->session->userdata('active_client_title');
						$flash_message  = '<p>Log Ingestion Time Zone settings were successfully created.</p>';
					}

					# Write To Logs
					$this->utility->write_log_entry('info', $log_message);

					# Success
					$this->session->set_userdata('my_flash_message_type', 'success');
					$this->session->set_userdata('my_flash_message', $flash_message);

					redirect('/account/preferences');
				}
				else
				{
					# Something went wrong
					$this->session->set_userdata('my_flash_message_type', 'error');
					$this->session->set_userdata('my_flash_message', '<p>Something went wrong. Please try again.</p>');
				}
			}
			else
			{
				$this->session->set_userdata('my_flash_message_type', 'success');
				$this->session->set_userdata('my_flash_message', '<p>No changes were made to the Log Ingestion Time Zone settings.</p>');

				redirect('/account/preferences');
			}
		}
		else
		{
			if (validation_errors()) 
			{
				$this->session->set_userdata('my_flash_message_type', 'error');
				$this->session->set_userdata('my_flash_message', validation_errors());
			}
		}

		$user_timezone          = $this->session->userdata('user_timezone');
		$data['user_timezone']  = $user_timezone;

		$data['timezone_dropdown'] = $this->get_timezone_dropdown($user_timezone);

		if (($notification_json = client_redis_info($this->client, $this->notification_field)) !== NULL)
		{
			$notification           = json_decode($notification_json, TRUE);

			$data['monthly_budget'] = $notification['monthly_budget'];
			$data['threshold']      = $notification['threshold'];
		}
		else
		{
			$data['monthly_budget'] = '';
			$data['threshold']      = '';
		}

		# Page Views
		$this->load->view('assets/header');
		$this->load->view('account/preferences/log-ingestion-settings', $data);
		$this->load->view('assets/footer');
	}

	private function get_timezone_dropdown($user_timezone)
	{
		$timezone_dropdown = array(
			'1' => $user_timezone.' (User time zone)',	
			'2' => 'UTC (Billing time zone)',
		);

		return $timezone_dropdown;
	}

	private function delete_redis_data($pattern)
	{
		$redis = new Redis();
		$redis->connect($this->redis_host, $this->redis_port, $this->redis_timeout);
		$redis->auth($this->redis_password);

			$arr_keys = $redis->keys($pattern);

			if (is_array($arr_keys) && !empty($arr_keys))
			{
				foreach ($arr_keys as $str_key)
				{
					$ret_val = $redis->del($str_key);
				}
			}

		$redis->close();
	}

	public function number_optional_digits($value)
	{
		if (strlen($value) === 0)
		{
			$this->form_validation->set_message('number_optional_digits', 'The {field} field is required.');
			return FALSE;
		}

		if (is_numeric($value) && $value <= 0)
		{
			$this->form_validation->set_message('number_optional_digits', 'The {field} field must contain a number greater than 0.');
			return FALSE;
		}

		$rv = preg_match('/^\d+(\.\d{2})?$/', $value);

		if ($rv === 0 || $rv === FALSE)
		{
			$this->form_validation->set_message('number_optional_digits', '{field} - invalid currency format.');
			return FALSE;
		}

		return TRUE;
	}
}