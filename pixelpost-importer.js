var update_current_status_timeout = 0;
var update_current_status_do = true;

function update_current_status() {
    jQuery.ajax({
        url:      ajaxurl,
        dataType: 'json',
        data:     {action: 'pp2wp_post_migration_status'}
    }).done(function(percentage) {
        if (update_current_status_do) {
           jQuery('#pp2wp_post_migration_log').html(percentage + '%  successfully imported!');
            update_current_status_timeout = setTimeout('update_current_status()', 2000);
        }
    });
}

jQuery(document).on('click', '#pp2wp_post_migration_stop', function(e) {
    jQuery.ajax({
        url:      ajaxurl,
        dataType: 'json',
        data:     {action: 'pp2wp_post_migration_stop'}
    }).done(function(p) {});
    e.preventDefault();
    return false;
});

jQuery(document).on('click', '#pp2wp_post_migration_resume', function(e) {
    jQuery.ajax({
        url:      ajaxurl,
        dataType: 'json',
        data:     {action: 'pp2wp_post_migration_resume'}
    }).done(function(p) {
        jQuery('#pp2wp_post_migration_log').html('All done! Migrated ' + p + ' posts!');
    });
    update_current_status_do = true;
    update_current_status_timeout = setTimeout('update_current_status()', 2000);
    e.preventDefault();
    return false;
});

jQuery(document).ready(function($) {
    jQuery.ajax({
        url:      ajaxurl,
        dataType: 'json',
        data:     {action: 'pp2wp_post_migration_start'}
    }).done(function(p) {
        jQuery('#pp2wp_post_migration_log').html('All done! Migrated ' + p + ' posts!');
        update_current_status_do = false;
        clearInterval(update_current_status_timeout);
    });
    update_current_status_timeout = setTimeout('update_current_status()', 2000);
});
