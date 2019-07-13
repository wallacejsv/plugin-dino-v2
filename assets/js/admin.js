jQuery(document).ready(function () { 

    //block edit, delete, posts dino api
    jQuery(`.wp-list-table.posts #the-list tr.category-${objeto_javascript.id_category_dino_slug}`).css("pointer-events", "none");
    jQuery(`.wp-list-table.posts #the-list tr.category-${objeto_javascript.id_category_dino_slug}`).css("display", "none");
    jQuery(`.wp-list-table.posts #the-list tr.category-${objeto_javascript.id_category_dino_slug}`).hide();

    //block edit, delete, category dino
    jQuery(`#the-list tr#tag-${objeto_javascript.id_category_dino}`).css("pointer-events", "none");

    jQuery(".com-imagem-dino").on("click", function(){
        alert("Os próximos conteúdos serão apenas com imagens. Para os anteriores, não haverá alteração.");
    });
});