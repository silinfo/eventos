/* Registro del service worker y botón «Instalar app» */
(function () {
  'use strict';
  if ('serviceWorker' in navigator) {
    window.addEventListener('load', function () {
      navigator.serviceWorker.register('sw.js').catch(function () {});
    });
  }

  var boton = document.getElementById('instalar-app');
  if (!boton) return;
  var standalone = window.matchMedia('(display-mode: standalone)').matches || window.navigator.standalone;
  if (standalone) return;

  var aviso = null;
  window.addEventListener('beforeinstallprompt', function (e) {
    e.preventDefault();
    aviso = e;
    boton.hidden = false;
  });
  window.addEventListener('appinstalled', function () { boton.hidden = true; });

  // iPhone/iPad: no hay aviso automático, se explica cómo hacerlo
  var ios = /iphone|ipad|ipod/i.test(navigator.userAgent) ||
    (navigator.platform === 'MacIntel' && navigator.maxTouchPoints > 1);
  if (ios) boton.hidden = false;

  boton.addEventListener('click', function () {
    if (aviso) {
      aviso.prompt();
      aviso.userChoice.then(function () { aviso = null; boton.hidden = true; });
    } else if (ios) {
      alert('Para instalar la app en tu iPhone o iPad:\n\n1. Pulsa el botón Compartir (cuadrado con flecha) en Safari.\n2. Elige «Añadir a pantalla de inicio».');
    }
  });
})();
