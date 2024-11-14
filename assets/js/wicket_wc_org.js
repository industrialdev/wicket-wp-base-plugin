jQuery(document).ready(function($) {
  let val = ''
  $('#wc-org-search').on('focusin', function() {
    val = $(this).val();
    console.log('Storing value on focus:', val);
    this.value = '';
  });

  $('#wc-org-results').on('click', function(event) {
    $('#wc-org-search').css('border','solid 1px orange');
    var preventUnload = true;
    var saveOrderButtons = document.querySelectorAll('.save_order');
    saveOrderButtons.forEach(function(button) {
        button.addEventListener('click', function() {
            console.log('Save order button clicked');
            preventUnload = false;
        });
    });
    window.addEventListener("beforeunload", function(event) {
        if (preventUnload) {
            var message = "You have modified the org assignment for this order. Are you sure you want to leave without saving?";
            event.returnValue = message;
            return message;
        }
    });
  });

  $('#wc-org-search').on('blur', function() {
    var searchTerm = $(this).val();
    console.log('Restoring value on blur:', val);
    console.log('SearchTerm:', searchTerm );
    if( searchTerm == '' ) {
      $(this).val(val);
    }
    $('#wc-org-results').css('border', 'none');
  });

  $('#wc-org-search').on('keyup', function() {
      var searchTerm = $(this).val();
      console.log('search');
      console.log(searchTerm);

      if (searchTerm.length < 3) {
          $('#wc-org-results').empty();
          return;
      }

      $.ajax({
          url: ajax_object.ajax_url,
          method: 'POST',
          data: {
              action: 'wc_org_search',
              term: searchTerm,
              nonce: $('#wc_org_nonce_field').val(),
          },
          success: function(data) {
              var resultsContainer = $('#wc-org-results');
              resultsContainer.empty();

              if (data.data.length > 0) {
                  $.each(data.data, function(index, item) {
                      resultsContainer.append('<div class="result-item" data-id="' + item.id + '">' + item.name + '</div>');
                  });
              } else {
                  resultsContainer.append('<div class="no-results">No results found</div>');
              }
              resultsContainer.show();
          },
          error: function(data) {
            console.dir(data);
          }
      });
  });

  $('#wc-org-results').on('click', '.result-item', function() {
      var selectedId = $(this).data('id');
      $('#wc-org-search').val($(this).text());
      $('#wc-org-search-id').val(selectedId);
      $('#wc-org-results').empty();
  });
});