<?php defined('BASEPATH') OR exit('No direct script access allowed'); ?>

<?php 
	$month_year     = (new DateTime())->format('M Y');
	$user_timezone  = $this->session->userdata('user_timezone');
?>

<section>
	<p>
		The Log Ingestion page displays the OpenObserve total usage, month-to-date cost and monthly usage chart for the current month. By hovering over 
		the vertical columns of the usage chart, you can view the daily usage and cost.
	</p>

	<p>
		The time period dropdown menu <button class="btn btn-default btn-xs"><?php echo $month_year; ?> <span class="caret"></button> is located in the 
		top right corner. The default time period is the current month and year. This can be changed to view four months' (current and previous three months) 
		worth of log ingestion metrics.
	</p>

	<p>
		Log ingestion metrics (month, usage and bill) can be exported to CSV by clicking the <button class="btn btn-default btn-xs"><i class="fad fa-download"></i> Export</button> 
		button at the top of the page. Note: the usage and bill are calculated in the UTC time zone.
	</p>

	<p>
		A Log Ingestion Settings section has been added to the Company Settings section under the Preferences tab. Click the 
		<button class="btn btn-primary btn-xs">Details</button> button and follow the Log Ingestion Notification instructions to set up a budget threshold 
		notification or follow the instructions below.
	</p>

	<h4>Log Ingestion Notification</h4>
	<p>To set up a notification to help you stay on top of your log ingestion usage costs, perform the following:</p>
	<ul>
		<li>Enter your <strong>Monthly Budget</strong> to define the maximum amount you are willing to spend on usage each month.</li>
		<li>Enter a <strong>Threshold</strong> to choose when you want to be alerted - expressed as a percentage of your Monthly Budget.</li>
	</ul>
	<p>For example, if your Monthly Budget is set to $500 and the Threshold is 80%, you will receive a notification when your usage cost exceeds $400.</p>

	<p>
		A Log Ingestion Settings section has been added to the Company Settings section under the Preferences tab. Click the 
		<button class="btn btn-primary btn-xs">Details</button> button and follow the Log Ingestion Time Zone instructions to change the time zone that 
		the usage chart is displayed or follow the instructions below.
	</p>

	<h4>Log Ingestion Time Zone</h4>
	<p>This setting controls the time zone that the log ingestion monthly usage chart is displayed. It only affects this chart and none of the other charts.</p>
	<p>When set to <?php echo $user_timezone; ?> (User time zone), the chart is displayed in the time zone that is set in the My Account section.</p>
	<p>When set to UTC (Billing time zone), the chart is displayed in the UTC time zone. This is the time zone in which billing costs are calculated.</p>
</section>
