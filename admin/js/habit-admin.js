jQuery(document).ready(function ($) {
  $(".habit-delete").on("click", function (e) {
    e.preventDefault();

    const button = $(this);
    const habitId = button.data("id");
    const habitName = button.data("name");

    if (!confirm(`Delete habit "${habitName}"?`)) {
      return;
    }
    $.post(
      ajaxurl,
      {
        action: "delete_habit",
        habit_id: habitId,
        nonce: habitTracker.nonce,
      },
      function (response) {
        if (response.success) {
          button.closest("tr").fadeOut(300, function () {
            $(this).remove();
          });
        } else {
          alert("Something went wrong.");
        }
      }
    );
  });
});
