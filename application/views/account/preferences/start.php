<?php defined('BASEPATH') OR exit('No direct script access allowed'); ?>

<?php //echo $account_navbar; ?>

<div class="row">
	<div class="col-md-8">
		<div class="panel panel-default">
			<div class="panel-heading">
				<div class="clearfix">
					<div class="pull-left">
						<h3>Preferences <?php //echo user_guide_modal('company-settings/system-api'); ?></h3>
						<h4>Customize Your console</h4>
					</div>
					<div class="pull-right">
						<a href="/account/company-settings" class="btn btn-default btn-sm">Return To Company Settings</a>
					</div>
				</div>
			</div>
			<div class="panel-body">

				<div class="row">
					<div class="col-md-12">

						<table class="table gray-header valign-middle">
							<thead>
								<tr>
									<th>Setting</th>
									<th></th>
								</tr>
							</thead>
							<tbody>

								<?php if ($this->config->item('openobserve_api_enabled') && $this->utility->has_section_access('log_ingestion')) { ?>
								<tr>
									<td>Log Ingestion Settings</td>
									<td class="text-right">
										<a class="btn btn-primary btn-xs" href="/account/preferences/log-ingestion-settings">Details</a>
									</td>
								</tr>
								<?php } ?>

							</tbody>
						</table>
		
					</div>
				</div>
				
			</div>
		</div>
	</div>
	
	
</div>

