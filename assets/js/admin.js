jQuery(document).ready(function () { 

    //block edit, delete, posts dino api
    jQuery(".wp-list-table.posts #the-list tr.category-dino").css("pointer-events", "none");
    jQuery(".wp-list-table.posts #the-list tr.category-dino").css("display", "none");
    jQuery(".wp-list-table.posts #the-list tr.category-dino .row-actions").hide();
});