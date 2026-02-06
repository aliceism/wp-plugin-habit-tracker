jQuery(document).ready(function ($) {
  function showNotice(message, type = "success") {
    const notice = $(`
            <div class="notice notice-${type} is-dismissible">
            <p>${message}</p>
            </div>`);

    $(".wrap h1").after(notice);

    notice.on("click", ".notice-dismiss", function () {
      notice.remove();
    });
  }
  $("#habit-form").on("submit", function (e) {
    e.preventDefault();

    const form = this;

    $.post(
      habitTracker.ajax_url,
      {
        action: "add_habit",
        nonce: habitTracker.nonce,
        habit_name: $("#habit_name").val(),
        habit_category: $("#habit_category").val(),
      },
      function (response) {
        if (!response.success) {
          showNotice(response.data.message, "error");
          return;
        }

        showNotice(response.data.message, "success");

        $("#habits-table tbody").append(response.data.row);

        form.reset();
      }
    );
  });

  $(".habit-delete").on("click", function (e) {
    e.preventDefault();
    const button = jQuery(this);
    const habitId = button.data("id");
    const habitName = button.data("name");

    if (!confirm(`Are you sure you want to delete "${habitName}"?`)) {
      return;
    }
    $.post(
      habitTracker.ajax_url,
      {
        action: "delete_habit",
        nonce: habitTracker.nonce,
        habit_id: habitId,
      },
      function (response) {
        if (!response.success) {
          showNotice(response.data.message, "error");
          return;
        }
        button.closest("tr").fadeOut(300, function () {
          $(this).remove();
        });
        showNotice(response.data.message, "success");
      }
    );
  });
});
