$(function () {

    $("#tags-list").on("click", "a", function () {
        $("#search-keyword").val($(this).text());
        $("#search-form").submit();
    });

    $("#tags-list a").each(function () {
        $(this).on("click", function () {
            $("#search-keyword").val($(this).text());
            $("#search-form").submit();
        })
    });
    $(".link a[href='" + Tiny.url.url_index + "']").addClass("current");
});