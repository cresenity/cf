<?php
/**
 * Description of Method
 *
 * @author Hery
 */
?>
<div class="card mb-3">
    <div class="card-header with-elements">
        <div class="card-header-title"><?php echo $name; ?> [<?php echo $className; ?>::<?php echo $method; ?>]</div>
        <div class="card-header-elements ml-auto">
            <a class="btn btn-success btn-run-method" id="<?php echo $this->element()->id() . '-action'; ?>" data-class="<?php echo $className; ?>" data-method="<?php echo $method; ?>" data-container-id="<?php echo $this->element()->id(); ?>" href="javascript:;"><i class="fas fa-play"></i> Check</a>
        </div>
    </div>
    <div class="card-body overflow-auto">
        <div id="<?php echo $this->element()->id(); ?>-result" class="my-4">

        </div>

    </div>
</div>




<script>


    $('#<?php echo $this->element()->id(); ?>-action').click(function (e) {
        var containerId = '<?php echo $this->element()->id(); ?>';
        var baseUrl = '<?php echo $controllerUrl; ?>';
        $('#' + containerId + '-result').empty();


        var className = $(this).attr('data-class');
        var method = $(this).attr('data-method');

        var currentElement = $('<div>').addClass('pb-1 mb-3');
        var loadingHtml = '<div class="sk-wave sk-primary"><div class="sk-rect sk-rect1"></div><div class="sk-rect sk-rect2"></div><div class="sk-rect sk-rect3"></div><div class="sk-rect sk-rect4"></div><div class="sk-rect sk-rect5"></div></div>';
        var elementStatus = $('<div>').addClass('db-connection-status text-right');
        elementStatus.append(loadingHtml);
        var elementInfo = $('<span>').addClass('checker-info');
        var elementError = $('<span>').addClass('checker-error text-danger d-none');

        currentElement.append(elementStatus);
        currentElement.append(elementInfo);
        currentElement.append('<br/>');
        currentElement.append(elementError);

        var url = baseUrl + 'check/' + className + '/' + method;
        $('#' + containerId + '-result').append(currentElement);

        $.ajax({
            url: url,
            cache: false,
            method: 'post',
            dataType: 'json',
            data: {},
            success: function (response) {

                var data = response.data;
                var consoleOutput = $('<div>').addClass('console p-3');
                elementInfo.append(consoleOutput);
                consoleOutput.append(data.output);
                if (response.errCode > 0) {
                    elementError.html(response.errMessage).removeClass('d-none');
                    var badgeElement = $('<span>').addClass('badge badge-outline-danger');
                    badgeElement.append('ERROR');
                    elementStatus.html('').append(badgeElement);
                } else {
                    var badgeElement = $('<span>').addClass('badge badge-outline-success');
                    badgeElement.append('OK');
                    elementStatus.html('').append(badgeElement);

                }
            },
            error: function (xhr, ajaxOptions, thrownError) {
                var badgeElement = $('<span>').addClass('badge badge-outline-danger');
                badgeElement.append('ERROR');
                elementStatus.html('').append(badgeElement);
                elementError.html(thrownError).removeClass('d-none');
            },
            complete: function () {


            },
        });
    });

</script>
