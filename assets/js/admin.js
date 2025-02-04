(() => {
  document.addEventListener("DOMContentLoaded", function () {
    document
      .getElementById("aster-import-form")
      .addEventListener("submit", function (e) {
        e.preventDefault();

        const loader = document.getElementById("aster-loader");
        const button = document.getElementById("aster-import-btn");

        loader.style.display = "flex"; // Mostrar el loader
        button.disabled = true;
        button.value = "Importando...";

        const formData = new FormData();
        formData.append("action", "aster_import_products_ajax");
        formData.append("security", aster_ajax.security);

        fetch(ajaxurl, {
          method: "POST",
          body: formData,
        })
          .then((response) => response.json())
          .then((data) => {
            loader.style.display = "none"; // Ocultar loader
            button.disabled = false;
            button.value = "Importar Productos";

            const wrap = document.querySelector(".wrap");
            wrap.insertAdjacentHTML(
              "beforeend",
              `<div class="mt-4">${data.data}</div>`
            );
          })
          .catch(() => {
            loader.style.display = "none";
            button.disabled = false;
            button.value = "Importar Productos";

            const wrap = document.querySelector(".wrap");
            wrap.insertAdjacentHTML(
              "beforeend",
              '<div class="notice notice-error"><p>Error en la importación.</p></div>'
            );
          });
      });
  });
})();
