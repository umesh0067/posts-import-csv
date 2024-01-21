jQuery(document).ready(function ($) {
    var customUploader;

    $('#upload_image_button').click(function (e) {
        e.preventDefault();

        if (customUploader) {
            customUploader.open();
            return;
        }

        customUploader = wp.media.frames.file_frame = wp.media({
            title: 'Choose Image',
            button: {
                text: 'Choose Image'
            },
            multiple: false
        });

        customUploader.on('select', function () {
            var attachment = customUploader.state().get('selection').first().toJSON();
            $('#um_custom_widget_image').val(attachment.url);
        });

        customUploader.open();
    });
});
jQuery(document).ready(function ($) {
    var customUploader;

    $('.upload-image-button').click(function (e) {
        e.preventDefault();

        if (customUploader) {
            customUploader.open();
            return;
        }

        customUploader = wp.media.frames.file_frame = wp.media({
            title: 'Choose Image',
            button: {
                text: 'Choose Image'
            },
            multiple: false
        });

        customUploader.on('select', function () {
            var attachment = customUploader.state().get('selection').first().toJSON();
            $('#um_custom_widget_image').val(attachment.url);
        });

        customUploader.open();
    });
});
