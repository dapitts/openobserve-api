<?php
defined('BASEPATH') OR exit('No direct script access allowed');

class Log_ingestion extends CI_Controller 
{
	private $client;
	private $cost_gb;
	private $redis_host;
	private $redis_port;
	private $redis_timeout;
	private $redis_password;
	private $tz_preference;
	private $dropdown_months;
	private $utc_offset;
	private $tz_is_utc;

	public function __construct()
	{
		parent::__construct();

		$this->tank_auth->check_login_status();	
		$this->utility->check_section_access('log_ingestion');

		$this->client           = rtrim($this->config->item('seed_name'), '-');
		$this->cost_gb          = $this->config->item('ingestion_cost_gb');
		$this->redis_host       = $this->config->item('redis_client_host');
		$this->redis_port       = $this->config->item('redis_client_port');
		$this->redis_timeout    = $this->config->item('redis_client_timeout');
		$this->redis_password   = $this->config->item('redis_client_password');
		$this->dropdown_months  = $this->config->item('ingestion_dropdown_months') ?? 4;

		$this->utility->check_redis_connection($this->redis_host, $this->redis_port, $this->redis_password, 'Log Ingestion');

		// Determine if the log ingestion timezone preference is set to UTC
		$this->tz_preference    = client_redis_info($this->client, 'log_ingestion_timezone') === '2' ? 'UTC' : $this->session->userdata('user_timezone');

		date_default_timezone_set($this->tz_preference);

		$this->utc_offset       = $this->parse_utc_offset($this->get_utc_offset());
		$this->tz_is_utc        = $this->utc_offset['hours'] === 0 && $this->utc_offset['minutes'] === 0;

		$this->load->library('openobserve_api');
	}

	function _remap($method)
	{ 
		if (method_exists($this, $method))
		{
			$this->$method();
		}
		else
		{
			$this->index($method);
		}
	}

	public function index($method = 'index')
	{
		$dt         = new DateTime();
		$year_full  = $dt->format('Y');
		$curr_year  = intval($year_full);
		$month_2dgt = $dt->format('m');
		$curr_month = intval($month_2dgt);
		$month_abbr = $dt->format('M');

		if ($method === 'index')
		{
			$interval           = $year_full.'-'.$month_2dgt;
			$interval_display   = $month_abbr.' '.$year_full;
		}
		else
		{
			// Example method: 2024_10
			$interval           = str_replace('_', '-', $method);
			$year_month         = explode('-', $interval);
			$year_full          = $year_month[0];
			$month_2dgt         = $year_month[1];
			$month_int          = intval($month_2dgt);
			$month_abbr         = $this->get_month_abbr_name($month_int);
			$interval_display   = $month_abbr.' '.$year_full;
		}

		$data['btn_text']           = $interval_display;
		$data['interval_dropdown']  = $this->get_interval_dropdown($curr_month, $curr_year, $this->dropdown_months);
		$data['interval']           = $interval;
		$data['timezone']           = $this->tz_preference;

		# Page Views
		$this->load->view('assets/header');
		$this->load->view('console/devices/log_ingestion/start', $data);
		$this->load->view('assets/footer');
	}

	public function data()
	{
		$dt = new DateTime();

		$data       = [];
		$interval   = $this->uri->segment(5);  // 2024-10
		$year_month = explode('-', $interval);
		$year_full  = $year_month[0];
		$month_2dgt = $year_month[1];
		$month_int  = intval($month_2dgt);
		$month_abbr = $this->get_month_abbr_name($month_int);
		$redis_key  = 'log_ingestion_'.strtolower($month_abbr).'_'.$year_full;

		$curr_interval  = $dt->format('Y-m');
		$is_curr_intvl  = $curr_interval === $interval;
		$expire_time    = $is_curr_intvl ? 180 : 600;

		if (($data = $this->get_redis_data($redis_key)) === NULL)
		{
			$month_full = $this->get_month_full_name($month_int);
			$last_day   = $this->get_days_in_month($month_int);
			$ts_prefix  = $interval.'-';

			$start_time = strtotime($ts_prefix.'01T00:00:00');
			$end_time   = strtotime($ts_prefix.$last_day.'T23:59:59');

			$response   = $this->get_ingestion_chart($start_time, $end_time);
			$ingestion  = $this->format_total_ingestion_and_cost($response['total_bytes']);

			$data['total_ingestion']    = $ingestion['total'];
			$data['ingestion_cost']     = $ingestion['cost'];
			$data['chart_title']        = $month_full.' '.$year_full.' Monthly Usage';
			$data['cost_gb']            = $this->cost_gb;
			$data['categories']         = $response['categories'];
			$data['series_data']        = $response['data'];

			$this->set_redis_data($redis_key, $data, $expire_time);
		}
		else
		{
			if ($is_curr_intvl)
			{
				$day_2dgt   = $dt->format('d');
				$day_int    = intval($day_2dgt);
				$ts_prefix  = $interval.'-';

				$start_time = strtotime($ts_prefix.$day_2dgt.'T00:00:00');
				$end_time   = strtotime($ts_prefix.$day_2dgt.'T23:59:59');

				$response   = $this->get_ingestion_chart($start_time, $end_time, FALSE);

				$chart_data = $response['data'];

				if (count($chart_data) === 1)
				{
					$size_bytes = $chart_data[0];

					$data['series_data'][$day_int - 1] = $size_bytes;

					$total_bytes    = array_sum($data['series_data']);
				}
				else
				{
					$total_bytes    = 0;
				}

				$ingestion = $this->format_total_ingestion_and_cost($total_bytes); 

				$data['total_ingestion']    = $ingestion['total'];
				$data['ingestion_cost']     = $ingestion['cost'];

				$this->set_redis_data($redis_key, $data, $expire_time);
			}
		}

		header('Content-Type: application/json');
		echo json_encode($data);
	}

	private function get_total_ingestion_and_cost($start_time, $end_time)
	{
		$ingestion = [];

		$response = $this->openobserve_api->get_total_ingestion($this->client, $start_time, $end_time);

		if ($response['success'])
		{
			if ($response['response']['total'] === 1)
			{
				$ingestion_mb       = $response['response']['hits'][0]['total_ingestion'];
				$ingestion_bytes    = $ingestion_mb * 1000000;
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

				$ingestion['total'] = $total;
				$ingestion['cost']  = '$'.number_format($ingestion_mb / 1000 * $this->cost_gb, 2);
			}
			else
			{
				$ingestion['total'] = NULL;
				$ingestion['cost']  = NULL;
			}
		}
		else
		{
			$ingestion['total'] = NULL;
			$ingestion['cost']  = NULL;
		}

		return $ingestion;
	}

	private function get_usage_gb_and_cost($start_time, $end_time)
	{
		$ingestion = [];

		$response = $this->openobserve_api->get_total_ingestion($this->client, $start_time, $end_time);

		if ($response['success'])
		{
			if ($response['response']['total'] === 1)
			{
				$ingestion_mb   = $response['response']['hits'][0]['total_ingestion'];
				$ingestion_gb   = $ingestion_mb / 1000;

				if ($ingestion_gb == 0)
				{
					$total  = 0;
				}
				else
				{
					$total  = number_format($ingestion_gb, 2);
				}

				$ingestion['total'] = $total;
				$ingestion['cost']  = '$'.number_format($ingestion_gb * $this->cost_gb, 2);
			}
			else
			{
				$ingestion['total'] = NULL;
				$ingestion['cost']  = NULL;
			}
		}
		else
		{
			$ingestion['total'] = NULL;
			$ingestion['cost']  = NULL;
		}

		return $ingestion;
	}

	private function get_ingestion_chart($start_time, $end_time, $imputation = TRUE)
	{
		$chart      = [];
		$categories = [];
		$data       = [];
		$month_info = [];
		$days       = [];
		$month_pos  = 5;
		$day_pos    = 8;
		$t_pos      = 10;
		$interval   = $this->get_histogram_interval();

		$response = $this->openobserve_api->get_ingestion_histogram($this->client, $start_time, $end_time, $interval);

		if ($response['success'])
		{
			if ($response['response']['total'])
			{
				$total_bytes = 0;

				foreach ($response['response']['hits'] as $idx => $result)
				{
					$timestamp = strtotime($result['x_axis'].' UTC');

					if ($idx === 0)
					{
						if ($this->tz_is_utc)
						{
							// Example timestamp: 2024-10-01T00:00:00
							$month = intval(substr($result['x_axis'], $month_pos, 2));

							$month_info['days']         = $this->get_days_in_month($month);
							$month_info['ts_prefix']    = substr($result['x_axis'], 0, $day_pos);
							$month_info['ts_suffix']    = substr($result['x_axis'], $t_pos);
						}
						else
						{
							[$year, $month] = explode('-', date('Y-m', $timestamp));

							$month_info['days']         = $this->get_days_in_month(intval($month));
							$month_info['ts_prefix']    = $year.'-'.$month.'-';
							$month_info['ts_suffix']    = 'T00:00:00';
						}
					}

					$bytes          = $result['y_axis'] * 1000000;
					$total_bytes    += $bytes;

					$categories[]   = $timestamp * 1000;  // Convert to milliseconds
					$data[]         = $bytes;

					if ($this->tz_is_utc)
					{
						$days[] = intval(substr($result['x_axis'], $day_pos, 2));
					}
					else
					{
						$days[] = intval(date('j', $timestamp));
					}
				}

				if (!$this->tz_is_utc)
				{
					$this->normalize_data($categories, $data, $days, $month_info);
				}

				if ($imputation)
				{
					$this->handle_missing_data($categories, $data, $days, $month_info);
				}

				$chart['categories']    = $categories;
				$chart['data']          = $data;
				$chart['total_bytes']   = $total_bytes;
			}
			else
			{
				$chart['categories']    = [];
				$chart['data']          = [];
				$chart['total_bytes']   = 0;
			}
		}
		else
		{
			$chart['categories']    = [];
			$chart['data']          = [];
			$chart['total_bytes']   = 0;
		}

		return $chart;
	}

	private function get_histogram_interval()
	{
		$hours      = $this->utc_offset['hours'];
		$minutes    = $this->utc_offset['minutes'];
		$interval   = '24 hours';  // Default to UTC

		if ($minutes === 0)
		{
			if ($hours % 2 === 0)  // Even
			{
				if ($hours === 0)  // UTC
				{
					$interval   = '24 hours';
				}
				else if ($hours === 10 || $hours === 14)
				{
					$interval   = '2 hours';
				}
				else
				{
					$interval   = $hours.' hours';
				}
			}
			else  // Odd
			{
				if ($hours === 3 || $hours === 9)
				{
					$interval   = '3 hours';
				}
				else
				{
					$interval   = '1 hour';
				}
			}
		}
		else
		{
			if ($minutes === 30)
			{
				$interval   = '30 minutes';
			}
			else if ($minutes === 45)
			{
				$interval   = '15 minutes';
			}
		}

		return $interval;
	}

	private function get_utc_offset()
	{
		$offset_seconds_str = date('Z');
		$offset_seconds_int = intval($offset_seconds_str);
		$offset_sign        = $offset_seconds_int < 0 ? '-' : '+';
		$offset_formatted   = gmdate('H:i', abs($offset_seconds_int));
		$utc_offset         = "UTC${offset_sign}${offset_formatted}";

		return $utc_offset;
	}

	private function parse_utc_offset($utc_offset)
	{
		$offset = [];

		// Example UTC offset: UTC-04:00
		$hours_pos  = 4;
		$mins_pos   = 7;
		$hours_str  = substr($utc_offset, $hours_pos, 2);
		$mins_str   = substr($utc_offset, $mins_pos);

		$offset['hours']    = intval($hours_str);
		$offset['minutes']  = intval($mins_str);

		return $offset;
	}

	private function format_total_ingestion_and_cost($ingestion_bytes)
	{
		$ingestion = [];

		if (!empty($ingestion_bytes))
		{
			$ingestion_gb   = $ingestion_bytes / 1000000000;
			$index          = intval(floor(log($ingestion_bytes) / log(1000)));
			$sizes          = ['B', 'KB', 'MB', 'GB', 'TB', 'PB'];

			if ($index === 0)
			{
				$total  = $ingestion_bytes.' '.$sizes[$index];
			}
			else
			{
				$total  = number_format($ingestion_bytes / (1000 ** $index), 2).' '.$sizes[$index];
			}

			$ingestion['total'] = $total;
			$ingestion['cost']  = '$'.number_format($ingestion_gb * $this->cost_gb, 2);
		}
		else
		{
			$ingestion['total'] = NULL;
			$ingestion['cost']  = NULL;
		}

		return $ingestion;
	}

	private function get_days_in_month($month)
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

	private function get_month_abbr_name($month)
	{
		$month_abbr_names = ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'];

		return $month_abbr_names[$month - 1];
	}

	private function get_month_full_name($month)
	{
		$month_full_names = ['January', 'February', 'March', 'April', 'May', 'June', 'July', 'August', 'September', 'October', 'November', 'December'];

		return $month_full_names[$month - 1];
	}

	private function get_interval_dropdown($month, $year, $num_months)
	{
		$intervals = [];

		if ($num_months > 12)
		{
			$num_months = 12;
		}

		for ($i = 0; $i < $num_months; $i++)
		{
			$interval['interval']   = $year.'-'.($month < 10 ? '0'.$month : $month);
			$month_abbr_name        = $this->get_month_abbr_name($month);
			$interval['month_year'] = $month_abbr_name.' '.$year;

			$intervals[] = $interval;

			$month--;

			if ($month === 0)
			{
				$month = 12;
				$year--;
			}
		}

		return $intervals;
	}

	private function normalize_data(&$categories, &$data, &$days, $month_info)
	{
		$orig_data  = $data;
		$orig_days  = $days;
		$temp_cats  = [];
		$temp_data  = [];
		$categories = [];
		$data       = [];
		$days       = [];

		foreach ($orig_days as $idx => $day)
		{
			if (!isset($temp_cats[$day]))
			{
				$ts_day = $day < 10 ? '0'.$day : $day;

				$temp_cats[$day]    = strtotime($month_info['ts_prefix'].$ts_day.$month_info['ts_suffix'].' UTC') * 1000;  // Convert to milliseconds
				$temp_data[$day]    = $orig_data[$idx];
			}
			else
			{
				$temp_data[$day]    += $orig_data[$idx];
			}
		}

		foreach ($temp_cats as $day => $category)
		{
			$categories[]   = $category;
			$data[]         = $temp_data[$day];
			$days[]         = $day;
		}
	}

	private function handle_missing_data(&$categories, &$data, $days, $month_info)
	{
		$num_days = count($days);

		if ($num_days === $month_info['days'])
		{
			return;
		}
		else  // Missing data
		{
			$orig_cats  = $categories;
			$orig_data  = $data;
			$categories = [];
			$data       = [];

			for ($i = 1, $j = 0; $i <= $month_info['days']; $i++)
			{
				if ($j < $num_days && $i === $days[$j])
				{
					$categories[]   = $orig_cats[$j];
					$data[]         = $orig_data[$j];

					$j++;
				}
				else
				{
					$day = $i < 10 ? '0'.$i : $i;

					$categories[]   = strtotime($month_info['ts_prefix'].$day.$month_info['ts_suffix'].' UTC') * 1000;  // Convert to milliseconds
					$data[]         = 0;
				}
			}
		}
	}

	public function export()
	{
		$data['cookie_token']   = time();
		$data['download_type']  = $this->uri->segment(5) ?? 'csv';

		$this->load->view('console/devices/log_ingestion/export', $data);		
	}

	public function do_export()
	{
		$this->form_validation->set_rules('download_type', 'download_type', 'trim|required');
		$this->form_validation->set_rules('download_token', 'download_token', 'trim|required');
		$this->form_validation->set_rules('download_image', 'download_image', 'trim');

		if ($this->form_validation->run()) 
		{
			$download_type  = $this->input->post('download_type');
			$download_token = $this->input->post('download_token');
			$download_image = $this->input->post('download_image');

			date_default_timezone_set('UTC');

			$dt         = new DateTime();
			$year_full  = $dt->format('Y');
			$year_int   = intval($year_full);
			$month_2dgt = $dt->format('m');
			$month_int  = intval($month_2dgt);
			$data       = [];

			for ($i = 0; $i < 3; $i++)
			{
				$month      = $month_int < 10 ? '0'.$month_int : $month_int;
				$month_full = $this->get_month_full_name($month_int);
				$last_day   = $this->get_days_in_month($month_int);
				$ts_prefix  = $year_int.'-'.$month.'-';

				$start_time = strtotime($ts_prefix.'01T00:00:00');
				$end_time   = strtotime($ts_prefix.$last_day.'T23:59:59');

				$response   = $this->get_usage_gb_and_cost($start_time, $end_time);

				$ingestion  = new stdClass();
				$ingestion->month   = $month_full.' '.$year_int;
				$ingestion->usage   = $response['total'] ?? 'N/A';
				$ingestion->bill    = $response['cost'] ?? 'N/A';

				$data[] = $ingestion;

				$month_int--;

				if ($month_int === 0)
				{
					$month_int = 12;
					$year_int--;
				}
			}

			$this->load->helper('cookie');
			$cookie = array(
				'name'      => 'export_token',
				'value'     => $download_token,
				'expire'    => 0,
				'secure'    => TRUE,
				'httponly'  => FALSE,
				'path'      => '/'
			);			
			$this->input->set_cookie($cookie);

			$filename = 'log_ingestion_export_'.date('Y-m-d').'.csv';
			$this->export_csv($data, $filename);
		}
	}

	private function export_csv($data, $filename)
	{
		$headers    = array('Month', 'Usage (GB)', 'Bill');
		$row        = array();

		header('Content-Type: text/csv');
		header('Content-Disposition: attachment; filename='.$filename);
		header('Expires: Thu, 19 Nov 1981 08:52:00 GMT');
		header('Cache-Control: no-store, no-cache, must-revalidate');
		header('Pragma: no-cache');

		if (($fp = fopen('php://output', 'w')) !== FALSE)
		{
			fputcsv($fp, $headers);

			foreach ($data as $ingestion)
			{
				$row[0] = $ingestion->month;
				$row[1] = $ingestion->usage;
				$row[2] = $ingestion->bill;

				fputcsv($fp, $row);
			}

			fclose($fp);
		}
	}

	private function get_redis_data($key)
	{
		$redis = new Redis();
		$value = NULL;

		$redis->connect($this->redis_host, $this->redis_port, $this->redis_timeout);
		$redis->auth($this->redis_password);

			if ($redis->exists($key))
			{
				$value = $redis->get($key);
			}

		$redis->close();

		if (!empty($value))
		{
			return json_decode($value, TRUE);
		}

		return NULL;
	}

	private function set_redis_data($key, $value, $expire_time = 300)
	{
		$redis = new Redis();
		$redis->connect($this->redis_host, $this->redis_port, $this->redis_timeout);
		$redis->auth($this->redis_password);

			$ret_val = $redis->set($key, json_encode($value), $expire_time);

		$redis->close();

		return $ret_val;
	}
}
