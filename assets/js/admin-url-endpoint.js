(() => {
  document.addEventListener("DOMContentLoaded", function () {
    const form = document.getElementById("aster-json-form");
    const inputField = document.getElementById("aster-json-url");
    const messageDiv = document.getElementById("aster-message");
    const saveBtn = document.getElementById("aster-save-btn");

    if (!form || !inputField || !messageDiv || !saveBtn) return;

    form.addEventListener("submit", function (e) {
      e.preventDefault(); // Evitar la recarga del formulario

      const newUrl = inputField.value;
      messageDiv.innerHTML = "Guardando...";
      saveBtn.disabled = true;

      fetch(aster_ajax.ajax_url, {
        method: "POST",
        headers: {
          "Content-Type": "application/x-www-form-urlencoded",
        },
        body: new URLSearchParams({
          // La funcion a donde yo quiero que vaya
          action: "aster_save_json_url",
          security: aster_ajax.security,
          json_url: newUrl,
        }),
      })
        .then((response) => response.json())
        .then((dataJson) => {
          const { success, data } = dataJson;
          if (success) {
            messageDiv.innerHTML = `<div class="notice notice-success"><p>${data.message}</p></div>`;
          } else {
            console.log("else", data);
            messageDiv.innerHTML = `<div class="notice notice-error"><p>${data.message}</p></div>`;
          }
          saveBtn.disabled = false;
        })
        .catch(() => {
          messageDiv.innerHTML = `<div class="notice notice-error"><p>Error al guardar.</p></div>`;
          saveBtn.disabled = false;
        });
    });
  });
})();
