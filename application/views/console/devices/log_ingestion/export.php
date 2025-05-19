<?php defined('BASEPATH') OR exit('No direct script access allowed'); ?>
<div class="modal-dialog" role="document">
	<div class="modal-content">
		<div class="modal-body">
			<div class="export-icons">
				<i class="circle"></i>
				<i class="<?php echo $download_type; ?>"></i>
			</div>
			<h3>Export <?php echo strtoupper($download_type); ?> File?</h3>		
			<h4>(limit on csv downloads)</h4>	
		</div>
		<div class="modal-footer">		

			<div class="alert modal-alert"></div>

			<div id="export-hidden-frame">
				<iframe name="print_iframe"></iframe>
			</div>

			<?php echo form_open('/console/devices/log-ingestion/do-export', array('id'=>'general-export-form', 'role'=>'form', 'class'=>'element-action-form', 'target'=>'print_iframe')); ?>

				<button type="button" class="btn btn-lg btn-danger" data-dismiss="modal">No</button>		
				<a id="exporter-test-clicker" class="btn btn-lg btn-success" data-loading-text="Generating Export...">Yes</a>

				<div class="hidden">
					<input type="hidden" name="download_token" value="<?php echo $cookie_token; ?>" />
					<input type="hidden" name="download_type" value="<?php echo $download_type; ?>" />
					<input type="hidden" name="download_image" id="pdf_image" value="" />
				</div>				

			<?php echo form_close(); ?>

		</div>
	</div>
</div>
