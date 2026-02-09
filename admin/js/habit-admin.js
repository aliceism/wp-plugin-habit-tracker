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

  function ensureEmptyState() {
    const tbody = $("#habits-table tbody");
    const rows = tbody.find("tr").not(".empty-row");

    if (rows.length === 0) {
      tbody.append(`
        <tr class="empty-row">
        <td colspan="4">No habits added yet.</td>
        </tr>`);
    }
  }

  function exitEditMode(row, name, category) {
    row.data("name", name);
    row.data("category", category);

    row.find(".habit-name").text(name);
    row.find(".habit-category").text(category);

    row.find(".habit-save, .habit-cancel").css("display", "none");
    row.find(".habit-edit").css("display", "inline-block");
  }

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
          ensureEmptyState();
        });
        showNotice(response.data.message, "success");
      }
    );
  });

  $("#habit-form").on("submit", function (e) {
    e.preventDefault();

    const form = this;
    const tbody = $("#habits-table tbody");

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

        tbody.find(".empty-row").remove();
        tbody.append(response.data.row);

        form.reset();
      }
    );
  });

  $(".habit-save, .habit-cancel").css("display", "none");

  $(document).on("click", ".habit-edit", function (e) {
    e.preventDefault();

    if ($(".habit-save:visible").length) {
      showNotice("Finish editing the current habit first.", "error");
      return;
    }

    const row = $(this).closest("tr");
    const name = row.data("name");
    const category = row.data("category");

    row
      .find(".habit-name")
      .html(`<input type="text" class="habit-name-input" value="${name}">`);

    row
      .find(".habit-category")
      .html(
        `<input type="text" class="habit-category-input" value="${category}">`
      );

    row.find(".habit-edit").css("display", "none");
    row.find(".habit-save, .habit-cancel").css("display", "inline-block");
  });

  $(document).on("click", ".habit-cancel", function (e) {
    e.preventDefault();

    const row = $(this).closest("tr");

    exitEditMode(row, row.data("name"), row.data("category"));
  });

  $(document).on("click", ".habit-save", function (e) {
    e.preventDefault();

    const row = $(this).closest("tr");
    const habitId = row.data("id");
    const name = row.find(".habit-name-input").val();
    const category = row.find(".habit-category-input").val();

    const saveBtn = row.find(".habit-save");

    saveBtn.text("Saving...");
    saveBtn.css("pointer-events", "none");

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
          exitEditMode(row, name, category);
          showNotice(response.data.message, "success");
        } else {
          showNotice(response.data.message, "error");
        }
      }
    ).always(function () {
      saveBtn.text("Save");
      saveBtn.css("pointer-events", "auto");
    });
  });
});
