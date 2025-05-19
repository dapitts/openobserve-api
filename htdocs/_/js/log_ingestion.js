(function($)
{
    $(document).ready(function()
    {
        if ($('div#log-ingestion').length)
        {
            let interval        = $('div#log-ingestion-interval').data('interval'),
                url             = '/console/devices/log_ingestion/data/'+interval,
                total_ingestion = $('.total-ingestion'),
                ingestion_cost  = $('.ingestion-cost');

            $.getJSON(url)
            .done(function(json)
            {
                if (json.total_ingestion === null)
                {
                    total_ingestion.html('-');
                }
                else
                {
                    total_ingestion.html(json.total_ingestion);
                }

                if (json.ingestion_cost === null)
                {
                    ingestion_cost.html('-');
                }
                else
                {
                    ingestion_cost.html(json.ingestion_cost);
                }

                load_log_ingestion_chart(json);

                // enable action buttons
                $('.action-btn').prop('disabled', false);
                $('#export-btn').removeClass('disabled');
            })
            .fail(function(jqxhr, textStatus, error) {
                console.log('Request failed: '+textStatus);
            });
        }
    });
})(jQuery);