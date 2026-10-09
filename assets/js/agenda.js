/* Agenda pública: cambio de vista, calendario mensual y ficha del evento */
(function () {
  'use strict';
  var A = window.AGENDA;
  var MESES = ['Enero', 'Febrero', 'Marzo', 'Abril', 'Mayo', 'Junio', 'Julio',
    'Agosto', 'Septiembre', 'Octubre', 'Noviembre', 'Diciembre'];
  var DIAS = ['Lun', 'Mar', 'Mié', 'Jue', 'Vie', 'Sáb', 'Dom'];

  var cache = {};           // id -> evento
  A.eventos.forEach(function (ev) { cache[ev.id] = ev; });

  function pad(n) { return (n < 10 ? '0' : '') + n; }
  function ymd(d) { return d.getFullYear() + '-' + pad(d.getMonth() + 1) + '-' + pad(d.getDate()); }
  function esc(s) {
    return String(s == null ? '' : s).replace(/[&<>"']/g, function (c) {
      return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c];
    });
  }

  /* ---------- Cambio de vista sin recargar ---------- */
  var tabs = document.querySelectorAll('.vistas a');
  tabs.forEach(function (a) {
    a.addEventListener('click', function (e) {
      e.preventDefault();
      mostrarVista(a.dataset.vista);
      history.replaceState(null, '', a.href);
    });
  });

  function mostrarVista(v) {
    tabs.forEach(function (a) { a.classList.toggle('activo', a.dataset.vista === v); });
    document.getElementById('vista-lista').hidden = v !== 'lista';
    document.getElementById('vista-calendario').hidden = v !== 'calendario';
    if (v === 'calendario' && !calIniciado) { calIniciado = true; pintarMes(); }
  }

  /* ---------- Ficha (modal) ---------- */
  var modal = document.getElementById('modal');
  var cuerpo = document.getElementById('modal-cuerpo');

  function abrir(id) {
    var ev = cache[id];
    if (!ev || !modal.showModal) return false;
    var meta = '';
    meta += '<li><b>Fecha</b>' + esc(ev.fechas_txt) + '</li>';
    if (ev.horario_txt) meta += '<li><b>Horario</b>' + esc(ev.horario_txt) + '</li>';
    if (ev.duracion) meta += '<li><b>Duración</b>' + esc(ev.duracion) + '</li>';
    if (ev.lugar) meta += '<li><b>Lugar</b>' + esc(ev.lugar) + '</li>';
    cuerpo.innerHTML =
      '<div class="ficha t-' + esc(ev.tipo) + '">' +
      '<span class="etiqueta">' + esc(ev.tipo_nombre) + '</span>' +
      '<h2>' + esc(ev.titulo) + '</h2>' +
      '<ul class="datos">' + meta + '</ul>' +
      (ev.descripcion ? '<div class="descripcion">' + esc(ev.descripcion).replace(/\n/g, '<br>') + '</div>' : '') +
      '<p class="acciones">' +
      (ev.url ? '<a class="btn" href="' + esc(ev.url) + '" target="_blank" rel="noopener">Más información ↗</a> ' : '') +
      '<a class="btn sec" href="evento.php?id=' + ev.id + '&ics=1">Añadir a mi calendario</a>' +
      '</p></div>';
    modal.showModal();
    return true;
  }

  document.addEventListener('click', function (e) {
    var a = e.target.closest('[data-id]');
    if (a && abrir(a.dataset.id)) e.preventDefault();
  });
  modal.addEventListener('click', function (e) { if (e.target === modal) modal.close(); });

  /* ---------- Calendario mensual ---------- */
  var calIniciado = false;
  var hoy = new Date();
  var mes = new Date(hoy.getFullYear(), hoy.getMonth(), 1);
  var cal = document.getElementById('calendario');

  document.querySelectorAll('[data-cal]').forEach(function (b) {
    b.addEventListener('click', function () {
      var acc = b.dataset.cal;
      if (acc === 'prev') mes.setMonth(mes.getMonth() - 1);
      else if (acc === 'next') mes.setMonth(mes.getMonth() + 1);
      else mes = new Date(hoy.getFullYear(), hoy.getMonth(), 1);
      pintarMes();
    });
  });

  function pintarMes() {
    document.getElementById('cal-titulo').textContent = MESES[mes.getMonth()] + ' ' + mes.getFullYear();
    // Rejilla de lunes a domingo
    var inicio = new Date(mes);
    inicio.setDate(1 - ((mes.getDay() + 6) % 7));
    var fin = new Date(mes.getFullYear(), mes.getMonth() + 1, 0);
    fin.setDate(fin.getDate() + (7 - ((fin.getDay() + 6) % 7) - 1));

    var url = A.api + '?desde=' + ymd(inicio) + '&hasta=' + ymd(fin) + (A.tipo ? '&tipo=' + encodeURIComponent(A.tipo) : '');
    cal.classList.add('cargando');
    fetch(url).then(function (r) { return r.json(); }).then(function (data) {
      data.eventos.forEach(function (ev) { cache[ev.id] = ev; });
      render(inicio, fin, data.eventos);
    }).catch(function () {
      cal.innerHTML = '<p class="vacio">No se pudieron cargar los eventos.</p>';
    }).then(function () { cal.classList.remove('cargando'); });
  }

  function render(inicio, fin, eventos) {
    var porDia = {};
    eventos.forEach(function (ev) {
      var d = new Date(ev.fecha + 'T00:00:00');
      var f = new Date((ev.fecha_fin || ev.fecha) + 'T00:00:00');
      for (; d <= f; d.setDate(d.getDate() + 1)) {
        (porDia[ymd(d)] = porDia[ymd(d)] || []).push(ev);
      }
    });

    var html = '<div class="cal-semana">' + DIAS.map(function (d) { return '<div>' + d + '</div>'; }).join('') + '</div><div class="cal-rejilla">';
    var hoyStr = ymd(hoy);
    for (var d = new Date(inicio); d <= fin; d.setDate(d.getDate() + 1)) {
      var k = ymd(d);
      var clases = 'cal-dia';
      if (d.getMonth() !== mes.getMonth()) clases += ' fuera';
      if (k === hoyStr) clases += ' hoy';
      var evs = porDia[k] || [];
      if (evs.length) clases += ' con-eventos';
      html += '<div class="' + clases + '"><span class="num">' + d.getDate() + '</span>';
      evs.forEach(function (ev) {
        html += '<button type="button" class="cal-ev t-' + esc(ev.tipo) + '" data-id="' + ev.id + '" title="' + esc(ev.titulo) + '">' +
          (ev.hora_inicio && ev.fecha === k ? '<span class="h">' + esc(ev.hora_inicio) + '</span> ' : '') + esc(ev.titulo) + '</button>';
      });
      html += '</div>';
    }
    html += '</div>';
    if (!eventos.length) html += '<p class="vacio">No hay eventos este mes.</p>';
    cal.innerHTML = html;
  }

  if (!document.getElementById('vista-calendario').hidden) { calIniciado = true; pintarMes(); }

  /* Ajuste de altura cuando está incrustado en un iframe del portal */
  if (A.embed && window.parent !== window) {
    var enviarAltura = function () {
      window.parent.postMessage({ suapEventosAltura: document.documentElement.scrollHeight }, '*');
    };
    new ResizeObserver(enviarAltura).observe(document.body);
  }
})();
