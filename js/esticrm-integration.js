jQuery(document).ready(function ($) {
    $('#esticrm-form').on('submit', saveIntegration);
    $('#esticrm-save-cpt-btn').on('click', saveCPT);
    $('#esticrm-save-field-mapping-btn').on('click', saveFieldMapping);
    $('#esticrm-start-mapping-btn').on('click', startMapping);
    $('#esticrm-save-mapping-btn').on('click', saveMapping);

    if ($('#esticrm-cpt').length) {
        loadCPTs();
    }

    function saveIntegration(e) {
        e.preventDefault();
        var id = $('#esticrm-id').val();
        var token = $('#esticrm-token').val();
        var nonce = $('#esticrm_nonce_field').val();
        var isRemove = $('#esticrm-save-btn').text() === 'Usuń integrację' ? 1 : 0;

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
                if (response.success) {
                    alert(response.data);
                    location.reload();
                } else {
                    alert('Error: ' + response.data);
                }
            },
            error: function (xhr, status, error) {
                alert('Error: ' + error);
            }
        });
    }

    function loadCPTs() {
        $.ajax({
            url: esticrm_ajax_obj.ajax_url,
            type: 'POST',
            dataType: 'json',
            data: {
                action: 'esticrm_get_cpts',
                esticrm_nonce: esticrm_ajax_obj.nonce
            },
            success: function (response) {
                if (response.success) {
                    var select = $('#esticrm-cpt');
                    select.empty();  // Upewnijmy się, że select jest pusty przed dodaniem nowych opcji
                    var selectedCpt = $('#esticrm-selected-cpt').val();
                    console.log('Loaded CPTs:', response.data);  // Dodajemy log danych CPT
                    response.data.forEach(function (cpt) {
                        select.append('<option value="' + cpt.value + '">' + cpt.name + '</option>');
                    });
                    if (selectedCpt) {
                        console.log('Setting selected CPT:', selectedCpt);  // Logujemy wybrany CPT
                        select.val(selectedCpt);
                        loadCPTFields(selectedCpt);
                        loadACFFields(selectedCpt);
                    }
                    select.on('change', function () {
                        var cpt = $(this).val();
                        loadCPTFields(cpt);
                        loadACFFields(cpt);
                    });
                } else {
                    alert('Failed to load CPTs: ' + response.data);
                }
            },
            error: function (xhr, status, error) {
                alert('Error: ' + error);
            }
        });
    }

    function saveCPT(e) {
        e.preventDefault();
        var cpt = $('#esticrm-cpt').val();
        $.ajax({
            url: esticrm_ajax_obj.ajax_url,
            type: 'POST',
            dataType: 'json',
            data: {
                action: 'esticrm_save_cpt',
                cpt: cpt,
                esticrm_nonce: esticrm_ajax_obj.nonce
            },
            success: function (response) {
                if (response.success) {
                    alert(response.data);
                    loadCPTFields(cpt);
                    loadACFFields(cpt);
                } else {
                    alert('Failed to save CPT: ' + response.data);
                }
            },
            error: function (xhr, status, error) {
                alert('Error: ' + error);
            }
        });
    }

    function loadCPTFields(cpt) {
        $.ajax({
            url: esticrm_ajax_obj.ajax_url,
            type: 'POST',
            dataType: 'json',
            data: {
                action: 'esticrm_get_cpt_fields',
                cpt: cpt,
                esticrm_nonce: esticrm_ajax_obj.nonce
            },
            success: function (response) {
                if (response.success) {
                    console.log('Loaded CPT fields for', cpt, ':', response.data);  // Logujemy dane pól CPT
                    var fieldsSelect = $('<select class="field-mapping-select">');
                    fieldsSelect.append('<option value="">Nie importuj</option>');
                    fieldsSelect.append('<option disabled>Pola Wordpress</option>');
                    fieldsSelect.append('<option value="title">Title</option>');
                    fieldsSelect.append('<option value="thumbnail">Thumbnail</option>');
                    fieldsSelect.append('<option value="excerpt">Excerpt</option>');
                    fieldsSelect.append('<option value="content">Content</option>');
                    fieldsSelect.append('<option disabled>Taksonomie</option>');
                    if (response.data.terms) {
                        $.each(response.data.terms, function (taxonomy, label) {
                            fieldsSelect.append('<option value="' + taxonomy + '">' + label + '</option>');
                        });
                    }
                    fieldsSelect.append('<option disabled>Pola ACF</option>');

                    $('#field-mapping-fields').empty().append(fieldsSelect);

                    // Restore previously saved field mapping
                    var savedFields = $('#esticrm-selected-acf-fields').val();
                    if (savedFields) {
                        try {
                            savedFields = JSON.parse(savedFields);
                            if (Array.isArray(savedFields)) {
                                savedFields.forEach(function (field) {
                                    fieldsSelect.find('option[value="' + field + '"]').prop('selected', true);
                                });
                            }
                        } catch (e) {
                            console.error('Error parsing saved fields:', e);
                        }
                    }
                } else {
                    alert('Failed to load CPT fields: ' + response.data);
                }
            },
            error: function (xhr, status, error) {
                alert('Error: ' + error);
            }
        });
    }

    function loadACFFields(cpt) {
        $.ajax({
            url: esticrm_ajax_obj.ajax_url,
            type: 'POST',
            dataType: 'json',
            data: {
                action: 'esticrm_get_acf_fields',
                cpt: cpt,
                esticrm_nonce: esticrm_ajax_obj.nonce
            },
            success: function (response) {
                if (response.success) {
                    console.log('Loaded ACF fields for', cpt, ':', response.data);  // Logujemy dane pól ACF
                    var acfFieldsHtml = '<h3>Pola ACF</h3>';
                    var fieldsSelect = $('.field-mapping-select');

                    $.each(response.data, function (name, label) {
                        acfFieldsHtml += '<div class="acf-field">';
                        acfFieldsHtml += '<input type="checkbox" class="acf-field-checkbox" data-name="' + name + '" data-label="' + label + '">';
                        acfFieldsHtml += '<label>' + label + '</label>';
                        acfFieldsHtml += '</div>';

                        // Dodajemy pola ACF do selecta
                        fieldsSelect.append('<option value="acf_' + name + '">' + label + '</option>');
                    });

                    $('#acf-fields').empty().append(acfFieldsHtml);

                    // Restore previously selected ACF fields
                    var selectedAcfFields = $('#esticrm-selected-acf-fields').val();
                    if (selectedAcfFields) {
                        try {
                            selectedAcfFields = JSON.parse(selectedAcfFields);
                            if (Array.isArray(selectedAcfFields)) {
                                selectedAcfFields.forEach(function (field) {
                                    $('.acf-field-checkbox[data-name="' + field + '"]').prop('checked', true);
                                    fieldsSelect.append('<option value="acf_' + field + '">' + field + '</option>');
                                });
                            }
                        } catch (e) {
                            console.error('Error parsing selected ACF fields:', e);
                        }
                    }

                    $('.acf-field-checkbox').on('change', function () {
                        var fieldName = $(this).data('name');
                        var fieldLabel = $(this).data('label');
                        if (this.checked) {
                            fieldsSelect.append('<option value="acf_' + fieldName + '">' + fieldLabel + '</option>');
                        } else {
                            fieldsSelect.find('option[value="acf_' + fieldName + '"]').remove();
                        }
                        updateSelectedFields();
                    });
                } else {
                    alert('Failed to load ACF fields: ' + response.data);
                }
            },
            error: function (xhr, status, error) {
                alert('Error: ' + error);
            }
        });
    }

    function saveFieldMapping(e) {
        e.preventDefault();
        var fields = [];
        $('#field-mapping-fields .field-mapping-select option:selected').each(function () {
            var field = $(this).val();
            if (field) {
                fields.push(field);
            }
        });

        $.ajax({
            url: esticrm_ajax_obj.ajax_url,
            type: 'POST',
            dataType: 'json',
            data: {
                action: 'esticrm_save_field_mapping',
                fields: fields,
                esticrm_nonce: esticrm_ajax_obj.nonce
            },
            success: function (response) {
                if (response.success) {
                    alert(response.data);
                    $('#mapping-table').hide();
                    $('#esticrm-save-mapping-btn').hide();
                    if ($('#start-integration-btn').length === 0) {
                        $('<button id="start-integration-btn">Rozpocznij integrację</button>').insertAfter('#mapping-table');
                    }
                } else {
                    alert('Failed to save field mapping: ' + response.data);
                }
            },
            error: function (xhr, status, error) {
                alert('Error: ' + error);
            }
        });
    }


    function updateSelectedFields() {
        var selectedFields = [];
        $('.acf-field-checkbox:checked').each(function () {
            selectedFields.push($(this).data('name'));
            console.log("zapisano checkbox o nazwie '" + $(this).data('name') + "'");
        });

        $.ajax({
            url: esticrm_ajax_obj.ajax_url,
            type: 'POST',
            dataType: 'json',
            data: {
                action: 'esticrm_save_acf_fields',
                acf_fields: selectedFields,
                esticrm_nonce: esticrm_ajax_obj.nonce
            },
            success: function (response) {
                if (response.success) {
                    console.log('Selected fields updated successfully');
                } else {
                    console.log('Failed to update selected fields: ' + response.data);
                }
            },
            error: function (xhr, status, error) {
                console.log('Error: ' + error);
            }
        });
    }

    function startMapping() {
        $.ajax({
            url: esticrm_ajax_obj.ajax_url,
            type: 'POST',
            dataType: 'json',
            data: {
                action: 'esticrm_start_mapping',
                esticrm_nonce: esticrm_ajax_obj.nonce
            },
            success: function (response) {
                if (response.success) {
                    var firstRecord = response.data;
                    var keys = Object.keys(firstRecord);
                    var values = Object.values(firstRecord);
                    var savedMapping = esticrm_ajax_obj.mapping;  // Pobieramy zapisane mapowanie

                    // Fetch CPT and ACF fields
                    $.when(
                        $.ajax({
                            url: esticrm_ajax_obj.ajax_url,
                            type: 'POST',
                            dataType: 'json',
                            data: {
                                action: 'esticrm_get_cpt_fields',
                                cpt: $('#esticrm-cpt').val(),
                                esticrm_nonce: esticrm_ajax_obj.nonce
                            }
                        }),
                        $.ajax({
                            url: esticrm_ajax_obj.ajax_url,
                            type: 'POST',
                            dataType: 'json',
                            data: {
                                action: 'esticrm_get_acf_fields',
                                cpt: $('#esticrm-cpt').val(),
                                esticrm_nonce: esticrm_ajax_obj.nonce
                            }
                        })
                    ).done(function (cptFieldsResponse, acfFieldsResponse) {
                        if (cptFieldsResponse[0].success && acfFieldsResponse[0].success) {
                            var cptFields = cptFieldsResponse[0].data;
                            var acfFields = acfFieldsResponse[0].data;

                            var mappingTableHtml = '<h3>Mapowanie Rekordu</h3>';
                            mappingTableHtml += '<table class="wp-list-table widefat fixed striped">';
                            mappingTableHtml += '<thead><tr><th>Key</th><th>Value</th><th>Select</th></tr></thead><tbody>';

                            keys.forEach(function (key, index) {
                                mappingTableHtml += '<tr>';
                                mappingTableHtml += '<td>' + key + '</td>';
                                mappingTableHtml += '<td>' + values[index] + '</td>';
                                mappingTableHtml += '<td><select class="field-mapping-select" name="' + key + '">';
                                mappingTableHtml += '<option value="">Nie importuj</option>';
                                mappingTableHtml += '<option disabled>Pola Wordpress</option>';
                                mappingTableHtml += '<option value="wordpress_title">Title</option>';
                                mappingTableHtml += '<option value="wordpress_thumbnail">Thumbnail</option>';
                                mappingTableHtml += '<option value="wordpress_excerpt">Excerpt</option>';
                                mappingTableHtml += '<option value="wordpress_content">Content</option>';
                                mappingTableHtml += '<option disabled>Taksonomie</option>';
                                if (cptFields.terms) {
                                    $.each(cptFields.terms, function (taxonomy, label) {
                                        mappingTableHtml += '<option value="taxonomy_' + taxonomy + '">' + label + '</option>';
                                    });
                                }
                                mappingTableHtml += '<option disabled>Pola ACF</option>';
                                $.each(acfFields, function (name, label) {
                                    mappingTableHtml += '<option value="acf_' + name + '">' + label + '</option>';
                                });
                                mappingTableHtml += '</select></td>';
                                mappingTableHtml += '</tr>';
                            });

                            mappingTableHtml += '</tbody></table>';
                            $('#mapping-table').html(mappingTableHtml);

                            // Ustawienie zapisanych wartości w select
                            $('#mapping-table tbody tr').each(function () {
                                var field = $(this).find('td:first').text();
                                var select = $(this).find('select.field-mapping-select');
                                if (savedMapping[field]) {
                                    select.val(savedMapping[field]);
                                }
                            });

                            $('#esticrm-save-mapping-btn').show();
                        } else {
                            alert('Failed to load fields for mapping');
                        }
                    }).fail(function () {
                        alert('Failed to fetch fields data');
                    });
                } else {
                    alert('Failed to load mapping record: ' + response.data);
                    console.log('Failed to load mapping record: ', response);
                }
            },
            error: function (jqXHR, textStatus, errorThrown) {
                alert('AJAX error: ' + textStatus + ' : ' + errorThrown);
                console.log('AJAX error: ', textStatus, errorThrown);
                console.log('Response Text: ', jqXHR.responseText);
            }
        });
    }

    function saveMapping() {
        var mapping = {};
        $('#mapping-table tbody tr').each(function () {
            var field = $(this).find('td:first').text();
            var selectedMapping = $(this).find('select.field-mapping-select').val();
            if (selectedMapping) {
                mapping[field] = selectedMapping;
            }
        });

        console.log('Saving mapping:', mapping); // Log mapping data

        $.ajax({
            url: esticrm_ajax_obj.ajax_url,
            type: 'POST',
            dataType: 'json',
            data: {
                action: 'esticrm_save_mapping',
                mapping: mapping,
                esticrm_nonce: esticrm_ajax_obj.nonce
            },
            success: function (response) {
                if (response.success) {
                    alert(response.data);
                    $('#mapping-table').hide();
                    $('#esticrm-save-mapping-btn').hide();
                    $('#start-integration-btn').show(); // Pokaż przycisk "Rozpocznij integrację"
                } else {
                    alert('Failed to save mapping: ' + response.data);
                }
            },
            error: function (xhr, status, error) {
                alert('Error: ' + error);
            }
        });
    }

    $('#start-integration-btn').on('click', startIntegration);

    function startIntegration() {
        $.ajax({
            url: esticrm_ajax_obj.ajax_url,
            type: 'POST',
            dataType: 'json',
            data: {
                action: 'esticrm_start_integration',
                esticrm_nonce: esticrm_ajax_obj.nonce
            },
            success: function (response) {
                if (response.success) {
                    var logHtml = '<div id="integration-log"><h3>Log integracji</h3><ul>';
                    response.data.log.forEach(function (message) {
                        logHtml += '<li>' + message + '</li>';
                    });
                    logHtml += '</ul></div>';
                    $('#integration-log').remove(); // Remove any existing log
                    $('#mapping-table').after(logHtml); // Append new log after the mapping table
                    console.log(response.data); // Optional: Log the integration details
                } else {
                    console.log('Failed to start integration: ' + response.data);
                }
            },
            error: function (xhr, status, error) {
                console.log('Error: ' + error);
            }
        });
    }



    $(document).on('click', '#start-integration-btn', startIntegration);

});
