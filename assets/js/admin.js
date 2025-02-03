(() => {
  // const initDomReady = () => {
  //   const $form = document.getElementById("aster-import-form");

  //   const handleSubmit = () => {
  //     document.getElementById("aster-loader").style.display = "block";
  //     document.getElementById("aster-import-btn").disabled = true;
  //   };

  //   $form && $form.addEventListener("submit", handleSubmit);
  // };
  // document.addEventListener("DOMContentLoaded", initDomReady);
  // console.log("jc-woocommerce-api-gateway");
  document.addEventListener("DOMContentLoaded", function () {
    document
      .getElementById("aster-import-form")
      .addEventListener("submit", function (e) {
        e.preventDefault(); // Evitar recarga

        var loader = document.getElementById("aster-loader");
        var button = document.getElementById("aster-import-btn");

        loader.style.display = "flex"; // Mostrar el loader
        button.disabled = true;
        button.value = "Importando...";

        var formData = new FormData();
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

            var wrap = document.querySelector(".wrap");
            wrap.insertAdjacentHTML("beforeend", data.data); // Mostrar mensaje de éxito o error
          })
          .catch(() => {
            loader.style.display = "none";
            button.disabled = false;
            button.value = "Importar Productos";

            var wrap = document.querySelector(".wrap");
            wrap.insertAdjacentHTML(
              "beforeend",
              '<div class="notice notice-error"><p>Error en la importación.</p></div>'
            );
          });
      });
  });
})();
