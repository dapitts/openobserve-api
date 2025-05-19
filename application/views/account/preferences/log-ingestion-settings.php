<?php defined('BASEPATH') OR exit('No direct script access allowed'); ?>

<div class="row">
	<div class="col-md-8">
		<div class="panel panel-default">
			<div class="panel-heading">
				<div class="clearfix">
					<div class="pull-left">
						<h3>Log Ingestion  <?php //echo user_guide_modal('company-settings/system-api'); ?></h3>
						<h4>Settings</h4>
					</div>
					<div class="pull-right">
						<a href="/account/preferences" class="btn btn-default btn-sm">Return To Preferences</a>						
						<a href="/account/company-settings" class="btn btn-default btn-sm">Return To Company Settings</a>
					</div>
				</div>
			</div>
			<div class="panel-body">
				<div class="row">
					<div class="col-md-12">
						<div class="preference-info">
							<h4>Log Ingestion Notification</h4>
							<p>To set up a notification to help you stay on top of your log ingestion usage costs, perform the following:</p>
							<ul>
								<li>Enter your <strong>Monthly Budget</strong> to define the maximum amount you are willing to spend on usage each month.</li>
								<li>Enter a <strong>Threshold</strong> to choose when you want to be alerted - expressed as a percentage of your Monthly Budget.</li>
							</ul>
							<p>For example, if your Monthly Budget is set to $500 and the Threshold is 80%, you will receive a notification when your usage cost exceeds $400.</p>
						</div>
					</div>
				</div>
				<?php echo form_open($this->uri->uri_string().'/update-notification'); ?>
				<div class="row">
					<div class="col-md-6">
						<div class="form-group<?php echo form_error('monthly_budget') ? ' has-error':''; ?>">
							<label class="control-label" for="monthly_budget">Monthly Budget</label>
							<div class="input-group">
								<div class="input-group-addon"><i class="fas fa-dollar-sign"></i></div>
								<input type="text" class="form-control" id="monthly_budget" name="monthly_budget" placeholder="Enter Monthly Budget" value="<?php echo set_value('monthly_budget', $monthly_budget); ?>">
							</div>
						</div>
					</div>
					<div class="col-md-6">
						<div class="form-group<?php echo form_error('threshold') ? ' has-error':''; ?>">
							<label class="control-label" for="threshold">Threshold</label>
							<div class="input-group">
								<input type="text" class="form-control" id="threshold" name="threshold" placeholder="Enter Threshold" value="<?php echo set_value('threshold', $threshold); ?>">
								<div class="input-group-addon"><i class="fas fa-percentage"></i></div>
							</div>
						</div>
					</div>
				</div>
				<?php if (!empty($monthly_budget) && !empty($threshold)) { ?>
				<div class="row">
					<div class="col-md-12">
						<div class="form-group">
							<input type="checkbox" id="delete_notification" name="delete_notification" value="1">
							<label for="delete_notification">Delete Notification</label>
						</div>
					</div>
				</div>
				<?php } ?>
				<div class="row">
					<div class="col-md-12 text-right">
						<button type="submit" class="btn btn-success" data-loading-text="Saving...">Save Settings</button>	
					</div>
				</div>
				<?php echo form_close(); ?>
			</div>
		</div>

		<div class="panel panel-default">
			<div class="panel-body">
				<div class="row">
					<div class="col-md-12">
						<div class="preference-info">
							<h4>Log Ingestion Time Zone</h4>
							<p>This setting controls the time zone that the log ingestion monthly usage chart is displayed. It only affects this chart and none of the other charts.</p>
							<p>When set to <?php echo $user_timezone; ?> (User time zone), the chart is displayed in the time zone that is set in the My Account section.</p>
							<p>When set to UTC (Billing time zone), the chart is displayed in the UTC time zone. This is the time zone in which billing costs are calculated.</p>
						</div>
					</div>
				</div>
				<?php echo form_open($this->uri->uri_string().'/update-timezone'); ?>
				<div class="row">
					<div class="col-md-6">
						<div class="form-group<?php echo form_error('timezone') ? ' has-error':''; ?>">
							<label class="control-label" for="timezone">Time Zone</label>
							<?php echo form_dropdown('timezone', $timezone_dropdown, ($this->input->post('timezone') ? $this->input->post('timezone') : $set_timezone), 'class="selectpicker form-control" id="timezone"'); ?>														
						</div>
					</div>
				</div>
				<div class="row">
					<div class="col-md-12 text-right">
						<button type="submit" class="btn btn-success" data-loading-text="Saving...">Save Settings</button>	
					</div>
				</div>
				<?php echo form_close(); ?>
			</div>
		</div>
	</div>
</div>
