<?php
defined('BASEPATH') OR exit('No direct script access allowed');

class Crons extends CI_Controller 
{

	function __construct()
	{
		parent::__construct();

		$this->load->model('Jobs_model', 'jobs');
	}

	public function process_ingestion_notifications()
	{
		if (is_cli())
		{
			if (($client_list = $this->jobs->get_active_clients()) !== NULL)
			{
				date_default_timezone_set('UTC');

				$dt         = new DateTime();
				$year_full  = $dt->format('Y');
				$month_2dgt = $dt->format('m');
				$month_int  = intval($month_2dgt);
				$month_full = $this->jobs->get_month_full_name($month_int);
				$last_day   = $this->jobs->get_days_in_month($month_int);
				$ts_prefix  = $year_full.'-'.$month_2dgt.'-';
				$start_time = strtotime($ts_prefix.'01T00:00:00');
				$end_time   = strtotime($ts_prefix.$last_day.'T23:59:59');

				foreach ($client_list as $client)
				{
					if (($notification = $this->jobs->get_log_ingestion_notification($client)) !== NULL)
					{
						if (($ingestion = $this->jobs->get_total_ingestion_and_cost($client->seed_name, $start_time, $end_time, $notification->cost_gb)) !== NULL)
						{
							$monthly_budget = floatval($notification->monthly_budget);
							$threshold      = intval($notification->threshold);
							$bdgt_threshold = $monthly_budget * $threshold / 100;

							if ($ingestion['cost'] > $bdgt_threshold)
							{
								$subject    = 'Log Ingestion Cost Has Exceeded Your Budget Threshold';
								$body       = 'The usage cost has exceeded '.$notification->threshold.'% of your monthly budget of $'.$notification->monthly_budget.'. ';
								$body       .= 'The current cost is '.$ingestion['formatted_cost'].'* for a usage of '.$ingestion['total'].' for the month of '.$month_full.'.'.PHP_EOL;
								$body       .= PHP_EOL;
								$body       .= '* Usage cost is based on the UTC time zone.';

								$communication_data = array(
									'client_id'         => $client->id,
									'expiration_date'   => date('Y-m-d H:i:s', strtotime('tomorrow')),
									'send_to_list'      => NULL,
									'subject'           => $subject,
									'body'              => $body,
									'console_only'      => 1,
									'number_sent'       => 0,
									'number_success'    => 0,
									'handler_id'        => 0,
								);

								$this->utility->insert_new_communication($communication_data);

								$email_list         = $this->jobs->get_email_distribution_list($client->id, $client->multi_client_enabled, $client->multi_client_link_id);
								$email_recipients   = array();

								if ($email_list['count'])
								{
									foreach ($email_list['data'] as $user)
									{
										if ($user->maintenance)  // Notifications email distribution list
										{
											$email_recipients[] = $user;
										}
									}
								}

								if (count($email_recipients))
								{
									foreach ($email_recipients as $recipient)
									{
										$email_body = 'Dear '.$recipient->first_name.' '.$recipient->last_name.','.PHP_EOL;
										$email_body .= PHP_EOL;
										$email_body .= 'This notification is from your Sagan console and is designed to help you manage your log ingestion usage costs and stay on top of your monthly budget.'.PHP_EOL;
										$email_body .= PHP_EOL;
										$email_body .= $body.PHP_EOL;
										$email_body .= PHP_EOL;
										$email_body .= 'What You Can Do:'.PHP_EOL;
										$email_body .= '1. Review Your Usage: Log into your Sagan console to see the latest log ingestion trends.'.PHP_EOL;
										$email_body .= '2. Adjust Your Budget or Threshold: If needed, you can modify your monthly budget or threshold in the Log Ingestion Notification preferences.'.PHP_EOL;
										$email_body .= '3. Optimize Usage: Consider reviewing and fine-tuning your ingestion pipelines to control future costs.'.PHP_EOL;
										$email_body .= PHP_EOL;
										$email_body .= 'If you have any questions or need assistance, our support team is here to help.'.PHP_EOL;
										$email_body .= PHP_EOL;
										$email_body .= 'Thank you for choosing Quadrant!';

										$email_data = array(
											'distribution_list' => 'Notifications',
											'body'              => $email_body
										);

										$send_mail_status = $this->utility->send_email('client-success-communication', $recipient->email, $subject, $email_data);
									}
								}
							}
						}
					}
				}
			}
		}
	}
}