/**
 * Load Page
 */
function loadPage()
{
  jQuery.ajax("./src/index.php?widgetId=" + widgetId + "&page=" + pageNumber, {
    success: function (htmlData) {
      jQuery("#openTicketsTable").empty().append(htmlData).append(function () {
        let horizontalScrollHeight = 0;
        if (jQuery("#openTicketsTable").outerWidth() < jQuery("#openTicketsTable").get(0).scrollWidth) {
            horizontalScrollHeight = 20;
        }
        const h = jQuery("#openTicketsTable").prop("scrollHeight") + horizontalScrollHeight;
        parent.iResize(window.name, h);
      });
    }
  });
  if (autoRefresh) {
    if (timeout) {
      clearTimeout(timeout);
    }
    timeout = setTimeout(loadPage, (autoRefresh * 1000));
  }
}

/**
 * Load toolbar
 */
function loadToolBar()
{
  jQuery("#toolBar").load(
    "./src/toolbar.php",
    {
      widgetId: widgetId
    }
  );
}


jQuery(function () {
  loadToolBar();
  loadPage();
  $('.checkall').live('click', function () {
    var chck = this.checked;
    $(this).parents().find(':checkbox').each(function () {
      $(this).attr('checked', chck);
      clickedCb[$(this).attr('id')] = chck;
    });
  });
  $(".selection").live('click', function () {
    clickedCb[$(this).attr('id')] = this.checked;
  });
});
