/**
 * DH Talleres — Frontend JS v1.3
 * NUEVO: precio variable al seleccionar medida
 * NUEVO: variantes dinámicas por tipo de producto
 * FIX: modal controlado por .dh-open (pointer-events: none cuando cerrado)
 */
(function ($) {
  'use strict';

  var currentTaller = {};

  /* ── ABRIR MODAL ────────────────────────────────── */
  $(document).on('click', '.dh-btn-inscribirse:not(.dh-btn-disabled)', function () {
    var $btn = $(this);
    currentTaller = {
      id:            parseInt($btn.data('taller-id'), 10),
      titulo:        $btn.data('taller-titulo')      || '',
      fecha:         $btn.data('taller-fecha')       || '',
      ubicacion:     $btn.data('taller-ubicacion')   || '',
      direccion:     $btn.data('taller-direccion')   || '',
      mapsUrl:       $btn.data('maps-url')           || '',
      tipoProducto:  $btn.data('tipo-producto')      || '',
      precioSena:    parseFloat($btn.data('precio-sena'))  || 0,
      precioTotal:   parseFloat($btn.data('precio-total')) || 0,
      // Precios base (para resetear al deseleccionar medida)
      precioSenaBase:  parseFloat($btn.data('precio-sena'))  || 0,
      precioTotalBase: parseFloat($btn.data('precio-total')) || 0,
      cuposMat:      parseInt($btn.data('cupos-mat'), 10)  || 0,
      cuposVes:      parseInt($btn.data('cupos-ves'), 10)  || 0,
      variantes:     {},
      medidas:       [],
    };

    try { currentTaller.variantes = JSON.parse($btn.attr('data-variantes') || '{}'); } catch(e) {}
    try { currentTaller.medidas   = JSON.parse($btn.attr('data-medidas')   || '[]'); } catch(e) {}

    dhResetModal();
    dhSetupTurnos();
    dhBuildVariantesForm();

    $('#dh-modal-subtitle').text(
      currentTaller.titulo + (currentTaller.fecha ? ' · ' + currentTaller.fecha : '')
    );
    dhUpdatePriceDisplay();

    $('#dh-modal-overlay').addClass('dh-open');
    $('body').css('overflow', 'hidden');
    setTimeout(function(){ $('#dh_nombre').focus(); }, 200);
  });

  /* ── CERRAR MODAL ───────────────────────────────── */
  function dhCloseModal() {
    $('#dh-modal-overlay').removeClass('dh-open');
    $('body').css('overflow', '');
  }
  $(document).on('click', '#dh-modal-close-btn', dhCloseModal);
  $(document).on('click', '#dh-modal-overlay', function(e){
    if ($(e.target).is('#dh-modal-overlay')) dhCloseModal();
  });
  $(document).on('keydown', function(e){
    if (e.key === 'Escape' && $('#dh-modal-overlay').hasClass('dh-open')) dhCloseModal();
  });

  /* ── RESET ──────────────────────────────────────── */
  function dhResetModal() {
    $('#dh-step-form').show();
    $('#dh-step-confirmacion').hide();
    var form = document.getElementById('dh-form-inscripcion');
    if (form) form.reset();
    $('#dh-form-error').hide().text('');
    $('.dh-option-card').removeClass('selected');
    $('input[type=radio]').prop('checked', false);
    $('input').removeClass('dh-error');
    $('#dh-btn-confirmar').prop('disabled', false);
    $('#dh-btn-confirmar .dh-btn-text').show();
    $('#dh-btn-confirmar .dh-btn-loader').hide();
  }

  /* ── SETUP TURNOS ───────────────────────────────── */
  function dhSetupTurnos() {
    var $mat = $('#dh-opt-matutino');
    var $ves = $('#dh-opt-vespertino');
    var sinMat = currentTaller.cuposMat <= 0;
    var sinVes = currentTaller.cuposVes <= 0;

    $mat.toggleClass('disabled', sinMat);
    $mat.find('input').prop('disabled', sinMat);
    $mat.find('.dh-option-cupos')
        .text(sinMat ? 'Sin cupos' : currentTaller.cuposMat + ' cupo' + (currentTaller.cuposMat !== 1 ? 's' : ''))
        .toggleClass('sin-cupos', sinMat);

    $ves.toggleClass('disabled', sinVes);
    $ves.find('input').prop('disabled', sinVes);
    $ves.find('.dh-option-cupos')
        .text(sinVes ? 'Sin cupos' : currentTaller.cuposVes + ' cupo' + (currentTaller.cuposVes !== 1 ? 's' : ''))
        .toggleClass('sin-cupos', sinVes);

    if (!sinMat) { $mat.find('input').prop('checked', true); $mat.addClass('selected'); }
    else if (!sinVes) { $ves.find('input').prop('checked', true); $ves.addClass('selected'); }
  }

  /* ── ACTUALIZAR DISPLAY DE PRECIOS ─────────────── */
  function dhUpdatePriceDisplay() {
    $('#dh-precio-sena-label').text('$' + dhFormatPrice(currentTaller.precioSena));
    $('#dh-precio-total-label').text('$' + dhFormatPrice(currentTaller.precioTotal));
  }

  /* ── CONSTRUIR FORM DE VARIANTES ────────────────── */
  function dhBuildVariantesForm() {
    var v       = currentTaller.variantes || {};
    var tipoSlug = currentTaller.tipoProducto || '';
    var tipoData = (dhPublic.tipos_producto && tipoSlug) ? (dhPublic.tipos_producto[tipoSlug] || {}) : {};

    // Si no hay tipo específico, usar el primero disponible
    if (!tipoData.colores && dhPublic.tipos_producto) {
      var slugs = Object.keys(dhPublic.tipos_producto);
      if (slugs.length) tipoData = dhPublic.tipos_producto[slugs[0]] || {};
    }

    var $grid    = $('#dh-variantes-grid');
    var $section = $('#dh-variantes-section');
    $grid.empty();

    var campos = [];
    if (v.color     && tipoData.colores    && tipoData.colores.length)     campos.push({ key:'color',     label:'🎨 Color',         opciones: tipoData.colores });
    if (v.tipo_lana && tipoData.tipos_lana && tipoData.tipos_lana.length)  campos.push({ key:'tipo_lana', label:'🧶 Tipo de lana',  opciones: tipoData.tipos_lana });
    if (v.micras    && tipoData.micras     && tipoData.micras.length)      campos.push({ key:'micras',    label:'🔬 Micras',         opciones: tipoData.micras });

    // Medidas: campo especial con actualización de precio
    if (v.medida && currentTaller.medidas && currentTaller.medidas.length) {
      campos.push({ key:'medida', label:'📏 Medida', esMediada: true, medidas: currentTaller.medidas });
    }

    if (!campos.length) { $section.hide(); return; }

    $.each(campos, function(i, campo) {
      var $group = $('<div class="dh-variante-select-group"></div>');
      var $label = $('<label>').text(campo.label);
      var $sel   = $('<select>').attr('name', 'variante_' + campo.key).attr('id', 'dh-var-' + campo.key);
      $sel.append('<option value="">— Elegí una opción —</option>');

      if (campo.esMediada) {
        // Opciones de medida: mostrar nombre + precios
        $.each(campo.medidas, function(j, m) {
          var txt = m.nombre;
          if (m.precio_sena && m.precio_total) {
            txt += '  (Seña $' + dhFormatPrice(m.precio_sena) + ' / Total $' + dhFormatPrice(m.precio_total) + ')';
          }
          $sel.append($('<option>').val(m.nombre).text(txt)
              .data('precio-sena', m.precio_sena || 0)
              .data('precio-total', m.precio_total || 0));
        });

        // Cuando se selecciona medida → actualizar precios
        $sel.on('change', function() {
          var $opt = $(this).find('option:selected');
          var pSena  = parseFloat($opt.data('precio-sena'))  || 0;
          var pTotal = parseFloat($opt.data('precio-total')) || 0;

          if (pSena > 0 && pTotal > 0) {
            currentTaller.precioSena  = pSena;
            currentTaller.precioTotal = pTotal;
          } else {
            // Volver a precio base
            currentTaller.precioSena  = currentTaller.precioSenaBase;
            currentTaller.precioTotal = currentTaller.precioTotalBase;
          }
          dhUpdatePriceDisplay();

          // Actualizar precio en la opción de pago seleccionada
          var tipoPago = $('input[name=tipo_pago]:checked').val();
          if (tipoPago === 'sena') {
            $('.dh-pago-card#dh-opt-sena').addClass('selected');
          }
          dhActualizarResumen();
        });

      } else {
        $.each(campo.opciones, function(j, op) {
          $sel.append($('<option>').val(op).text(op));
        });
      }

      $group.append($label).append($sel);
      $grid.append($group);
    });

    $section.show();
  }

  /* ── SELECCIÓN DE OPTION CARDS ──────────────────── */
  $(document).on('change', '.dh-turno-selector input[type=radio]', function() {
    $('.dh-turno-selector .dh-option-card').removeClass('selected');
    $(this).closest('.dh-option-card').addClass('selected');
    dhActualizarResumen();
  });
  $(document).on('change', '.dh-pago-selector input[type=radio]', function() {
    $('.dh-pago-selector .dh-option-card').removeClass('selected');
    $(this).closest('.dh-option-card').addClass('selected');
    dhActualizarResumen();
  });
  $(document).on('click', '.dh-option-card:not(.disabled)', function() {
    $(this).find('input[type=radio]').prop('checked', true).trigger('change');
  });
  $(document).on('input', '#dh_nombre', dhActualizarResumen);

  /* ── RESUMEN EN TIEMPO REAL ─────────────────────── */
  function dhActualizarResumen() {
    // No hay resumen separado en v1.3, los precios se actualizan en los botones
    dhUpdatePriceDisplay();
  }

  /* ── VALIDACIÓN ─────────────────────────────────── */
  function dhValidar() {
    var errors = [];
    var nombre   = $('#dh_nombre').val().trim();
    var email    = $('#dh_email').val().trim();
    var turno    = $('input[name=turno]:checked').val();
    var tipoPago = $('input[name=tipo_pago]:checked').val();

    $('#dh_nombre').toggleClass('dh-error', !nombre);
    $('#dh_email').toggleClass('dh-error', !email || !dhIsEmail(email));

    if (!nombre)             errors.push('Ingresá tu nombre completo.');
    if (!email)              errors.push('Ingresá tu correo electrónico.');
    else if (!dhIsEmail(email)) errors.push('El correo no es válido.');
    if (!turno)              errors.push('Seleccioná un turno.');
    if (!tipoPago)           errors.push('Seleccioná si abonás seña o total.');

    return errors;
  }

  /* ── SUBMIT ─────────────────────────────────────── */
  $(document).on('submit', '#dh-form-inscripcion', function(e) {
    e.preventDefault();
    var errors = dhValidar();
    if (errors.length) {
      $('#dh-form-error').html(errors.join('<br>')).show();
      setTimeout(function(){ document.getElementById('dh-form-error').scrollIntoView({behavior:'smooth',block:'center'}); }, 80);
      return;
    }
    $('#dh-form-error').hide();

    var $btn = $('#dh-btn-confirmar');
    $btn.prop('disabled', true);
    $btn.find('.dh-btn-text').hide();
    $btn.find('.dh-btn-loader').show();

    var turno    = $('input[name=turno]:checked').val();
    var tipoPago = $('input[name=tipo_pago]:checked').val();

    var postData = {
      action:    'dh_registrar',
      nonce:     dhPublic.nonce,
      taller_id: currentTaller.id,
      turno:     turno,
      tipo_pago: tipoPago,
      nombre:    $('#dh_nombre').val().trim(),
      email:     $('#dh_email').val().trim(),
      telefono:  $('#dh_telefono').val().trim(),
    };

    // Agregar variantes seleccionadas
    $('#dh-variantes-grid select').each(function() {
      var n = $(this).attr('name');
      var v = $(this).val();
      if (n && v) postData[n] = v;
    });

    $.ajax({
      url:  dhPublic.ajax_url,
      type: 'POST',
      data: postData,
      success: function(res) {
        if (res.success) {
          dhMostrarConfirmacion(res.data);
        } else {
          var msg = (res.data && res.data.msg) ? res.data.msg : 'Ocurrió un error. Intentá de nuevo.';
          $('#dh-form-error').html(msg).show();
          $btn.prop('disabled', false);
          $btn.find('.dh-btn-text').show();
          $btn.find('.dh-btn-loader').hide();
        }
      },
      error: function() {
        $('#dh-form-error').html('Error de conexión. Por favor intentá nuevamente.').show();
        $btn.prop('disabled', false);
        $btn.find('.dh-btn-text').show();
        $btn.find('.dh-btn-loader').hide();
      }
    });
  });

  /* ── PANTALLA DE CONFIRMACIÓN ───────────────────── */
  function dhMostrarConfirmacion(data) {
    $('#dh-step-form').hide();
    $('#dh-step-confirmacion').show();
    $('#dh-modal-box').scrollTop(0);

    // Card del taller
    var html = '<div class="dh-confirm-taller-titulo">🧶 ' + dhEscape(data.taller_titulo) + '</div>';
    if (data.taller_fecha) html += '<div class="dh-confirm-taller-row"><span class="dh-confirm-taller-label">📅 Fecha</span><span>' + dhEscape(data.taller_fecha) + '</span></div>';
    if (data.ubicacion)    html += '<div class="dh-confirm-taller-row"><span class="dh-confirm-taller-label">📍 Lugar</span><span>' + dhEscape(data.ubicacion) + '</span></div>';
    if (data.direccion)    html += '<div class="dh-confirm-taller-row"><span class="dh-confirm-taller-label">🏠</span><span>' + dhEscape(data.direccion) + '</span></div>';
    if (data.maps_url)     html += '<div class="dh-confirm-taller-row"><span></span><a href="' + dhEscape(data.maps_url) + '" target="_blank" style="color:#D4A96A;font-weight:600;">🗺️ Ver en Google Maps</a></div>';
    $('#dh-confirm-taller').html(html);

    // Detalles
    var precioFmt = '$' + dhFormatPrice(parseFloat(data.precio));
    var det = '<div class="dh-confirm-detalles-title">Tu inscripción</div>';
    det += '<div class="dh-confirm-row"><span class="dh-confirm-lbl">Nombre</span><span class="dh-confirm-val">'       + dhEscape(data.nombre)       + '</span></div>';
    det += '<div class="dh-confirm-row"><span class="dh-confirm-lbl">Email</span><span class="dh-confirm-val">'        + dhEscape(data.email)        + '</span></div>';
    det += '<div class="dh-confirm-row"><span class="dh-confirm-lbl">Turno</span><span class="dh-confirm-val">'        + dhEscape(data.turno_label)  + '</span></div>';
    det += '<div class="dh-confirm-row"><span class="dh-confirm-lbl">Tipo de pago</span><span class="dh-confirm-val">' + dhEscape(data.pago_label)   + '</span></div>';
    if (data.medida) {
      det += '<div class="dh-confirm-row"><span class="dh-confirm-lbl">Medida</span><span class="dh-confirm-val">'     + dhEscape(data.medida)       + '</span></div>';
    }
    det += '<div class="dh-confirm-row"><span class="dh-confirm-lbl">Monto</span><span class="dh-confirm-val dh-confirm-price">' + precioFmt + '</span></div>';
    $('#dh-confirm-detalles').html(det);

    // Variantes
    if (data.variantes && Object.keys(data.variantes).length > 0) {
      var vLabels = { color:'🎨 Color', tipo_lana:'🧶 Tipo de lana', micras:'🔬 Micras', medida:'📏 Medida' };
      var vHtml = '<div class="dh-confirm-var-title">Material seleccionado</div>';
      $.each(data.variantes, function(k, v) {
        if (v && k !== 'medida') {
          vHtml += '<div class="dh-confirm-row"><span class="dh-confirm-lbl">' + (vLabels[k]||k) + '</span><span class="dh-confirm-val">' + dhEscape(v) + '</span></div>';
        }
      });
      $('#dh-confirm-variantes').html(vHtml).show();
    } else {
      $('#dh-confirm-variantes').hide();
    }

    // CTA pago
    $('#dh-btn-pagar').attr('href', data.pay_url || '#');

    // Calendario
    if (data.taller_fecha) {
      if (data.google_cal) $('#dh-btn-google-cal').attr('href', data.google_cal);
      if (data.ics_url)    $('#dh-btn-ics').attr('href', data.ics_url);
      $('#dh-cal-section').show();
    } else {
      $('#dh-cal-section').hide();
    }
  }

  /* ── HELPERS ────────────────────────────────────── */
  function dhFormatPrice(n) {
    if (isNaN(n)) return '0';
    return n.toLocaleString('es-UY', { minimumFractionDigits:0, maximumFractionDigits:0 });
  }
  function dhIsEmail(v) { return /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(v); }
  function dhEscape(s) {
    return String(s||'').replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;');
  }

})(jQuery);
