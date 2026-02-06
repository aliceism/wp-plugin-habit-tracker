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

  $(document).on("click", ".habit-edit", function (e) {
    e.preventDefault();

    const row = $(this).closest("tr");
    const name = row.data("name");
    const category = row.data("category");

    row
      .find(".habit-name")
      .html(`<input type="text" class="habit-name-input" value="${name}"`);

    row
      .find(".habit-category")
      .html(
        `<input type="text" class="habit-category-input" value="${category}"`
      );

    row.find(".habit-edit").hide();
    row.find(".habit-save, .habit-cancel").show();
  });

  $(document).on("click", ".habit-cancel", function (e) {
    e.preventDefault();

    const row = $(this).closest("tr");

    row.find(".habit-name").text(row.data("name"));
    row.find(".habit-category").text(row.data("category"));

    row.find(".habit-save, .habit-cancel").hide();
    row.find(".habit-edit").show();
  });

  $(document).on("click", ".habit-save", function (e) {
    e.preventDefault();

    const row = $(this).closest("tr");
    const habitId = row.data("id");
    const name = row.find(".habit-name-input").val();
    const category = row.find(".habit-category-input").val();

    $.post(
      habitTracker.ajax_url,
      {
        action: "update_habit",
        nonce: habitTracker.nonce,
        habit_id: habitId,
        habit_name: name,
        habit_category: category,
      },
      function (response) {
        if (response.success) {
          row.data("name", name);
          row.data("category", category);

          row.find(".habit-name").text(name);
          row.find(".habit-category").text(category);

          row.find(".habit-save", ".habit-cancel").hide();
          row.find(".habit-edit").show();
          showNotice(response.data.message, "success");
        } else {
          showNotice(response.data.message, "error");
        }
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
