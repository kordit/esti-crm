jQuery(document).ready(function ($) {
    $('#esticrm-form').on('submit', function (e) {
        e.preventDefault();
        var id = $('#esticrm-id').val();
        var token = $('#esticrm-token').val();
        var nonce = $('#esticrm_nonce_field').val();
        var isRemove = $('#esticrm-save-btn').text() === 'Usuń integrację' ? 1 : 0;

        console.log('ID:', id, 'Token:', token, 'Nonce:', nonce, 'Remove:', isRemove);

        $.ajax({
            url: esticrm_ajax_obj.ajax_url,
            type: 'POST',
            dataType: 'json',
            data: {
                action: 'esticrm_save_integration',
                id: id,
                token: token,
                remove: isRemove,
                esticrm_nonce: nonce
            },
            success: function (response) {
                console.log('AJAX response:', response);
                if (response.success) {
                    alert(response.data);
                    location.reload(); // Reload the page to reflect changes
                } else {
                    alert('Error: ' + response.data);
                }
            },
            error: function (xhr, status, error) {
                console.log('AJAX error:', error, 'XHR:', xhr, 'Status:', status);
                alert('Error: ' + error);
            }
        });
    });

    $('#esticrm-run-btn').on('click', function () {
        var nonce = $('#esticrm_nonce_field').val();

        console.log('Running integration with nonce:', nonce);

        $.ajax({
            url: esticrm_ajax_obj.ajax_url,
            type: 'POST',
            dataType: 'json',
            data: {
                action: 'esticrm_run_integration',
                esticrm_nonce: nonce
            },
            success: function (response) {
                console.log('AJAX response:', response);
                if (response.success) {
                    var resultContainer = $('#esticrm-result');
                    resultContainer.empty();
                    var data = response.data.data; // Assuming offers are in response.data.data
                    data.forEach(function (offer) {
                        var elementHtml = '<div class="element-response">';
                        elementHtml += createResponseHtml(offer);
                        elementHtml += '</div>';
                        resultContainer.append(elementHtml);
                    });
                } else {
                    $('#esticrm-result').html('<pre>Error: ' + response.data + '</pre>');
                }
            },
            error: function (xhr, status, error) {
                console.log('AJAX error:', error, 'XHR:', xhr, 'Status:', status);
                $('#esticrm-result').html('<pre>Error: ' + error + '</pre>');
            }
        });
    });

    function createResponseHtml(data) {
        var html = '';
        for (const [key, value] of Object.entries(data)) {
            html += '<div class="single-response">';
            html += '<div class="key">' + key + '</div>';
            if (Array.isArray(value)) {
                html += '<div class="value">' + createArrayHtml(value) + '</div>';
            } else if (typeof value === 'object' && value !== null) {
                html += '<div class="value">' + createResponseHtml(value) + '</div>';
            } else {
                html += '<div class="value">' + value + '</div>';
            }
            html += '</div>';
        }
        return html;
    }

    function createArrayHtml(array) {
        var html = '<div class="array">';
        array.forEach(function (item) {
            if (typeof item === 'object' && item !== null) {
                html += createResponseHtml(item);
            } else {
                html += '<div class="array-item">' + item + '</div>';
            }
        });
        html += '</div>';
        return html;
    }
});
