/**
 * DH Talleres — Admin JS v1.5
 * Todo dentro de un único wrapper jQuery para que $ esté disponible en todas las funciones.
 */
(function ($) {
  'use strict';

  /* ═══════════════════════════════════════════════
     HELPERS GLOBALES
  ═══════════════════════════════════════════════ */
  function dhEsc(s) {
    return String(s || '').replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;');
  }
  function dhFmtNum(n) {
    return Number(n || 0).toLocaleString('es-UY', { minimumFractionDigits:0, maximumFractionDigits:0 });
  }
  function dhFlash(el, msg) {
    var orig = el.textContent;
    el.textContent = msg;
    setTimeout(function () { el.textContent = orig; }, 1800);
  }

  /* ═══════════════════════════════════════════════
     META BOX TABS
  ═══════════════════════════════════════════════ */
  window.dhTab = function (id, el) {
    document.querySelectorAll('.dh-meta-panel').forEach(function (p) { p.classList.remove('active'); });
    document.querySelectorAll('.dh-meta-tab').forEach(function (t)   { t.classList.remove('active'); });
    var panel = document.getElementById('dh-panel-' + id);
    if (panel) panel.classList.add('active');
    if (el)    el.classList.add('active');
  };

  /* ═══════════════════════════════════════════════
     COPY SHORTCODE
  ═══════════════════════════════════════════════ */
  window.dhCopyShortcode = function (el) {
    var text = el.textContent.trim();
    if (navigator.clipboard) {
      navigator.clipboard.writeText(text).then(function () { dhFlash(el, '✅ Copiado'); });
    } else {
      var tmp = document.createElement('textarea');
      tmp.value = text;
      document.body.appendChild(tmp);
      tmp.select();
      document.execCommand('copy');
      document.body.removeChild(tmp);
      dhFlash(el, '✅ Copiado');
    }
  };

  /* ═══════════════════════════════════════════════
     MAPS PREVIEW
  ═══════════════════════════════════════════════ */
  function dhPreviewMaps(url) {
    var $wrap = $('#dh-maps-preview-wrap');
    if (!url || !url.trim()) { $wrap.hide(); return; }
    if (url.indexOf('/embed') !== -1) {
      $wrap.html('<div class="dh-maps-preview"><iframe src="' + url + '" allowfullscreen loading="lazy" referrerpolicy="no-referrer-when-downgrade"></iframe></div>').show();
    } else {
      $wrap.html('<div style="margin-top:8px;"><a href="' + url + '" target="_blank" class="button button-small">🗺️ Ver en Google Maps</a><br><small style="color:#888;font-size:11px;margin-top:4px;display:block;">Para embed, usá la URL de "Compartir → Insertar mapa" de Google Maps.</small></div>').show();
    }
  }
  $(document).on('input change', '#dh_maps_url', function () { dhPreviewMaps($(this).val()); });
  $(function () { if ($('#dh_maps_url').length && $('#dh_maps_url').val()) dhPreviewMaps($('#dh_maps_url').val()); });

  /* ═══════════════════════════════════════════════
     DASHBOARD TALLERES: ORDENAR Y VISTA
  ═══════════════════════════════════════════════ */
  window.dhOrdenar = function (criterio) {
    var $grid   = $('#dh-talleres-grid');
    var $cards  = $grid.find('.dh-taller-card').toArray();

    $cards.sort(function (a, b) {
      var va = $(a).data(criterio) || '';
      var vb = $(b).data(criterio) || '';
      if (criterio === 'fecha') {
        return new Date(va || '9999') - new Date(vb || '9999');
      }
      return String(va).localeCompare(String(vb), 'es');
    });

    $cards.forEach(function (c) { $grid.append(c); });

    // Resaltar botón activo
    $('.dh-sort-btn').removeClass('active');
    $('.dh-sort-btn[data-sort="' + criterio + '"]').addClass('active');
  };

  window.dhSetVista = function (vista) {
    var $grid = $('#dh-talleres-grid');
    $grid.removeClass('dh-vista-grid dh-vista-lista').addClass('dh-vista-' + vista);
    $('.dh-view-btn').removeClass('active');
    $('.dh-view-btn[data-view="' + vista + '"]').addClass('active');
    try { localStorage.setItem('dh_vista', vista); } catch(e) {}
  };

  // Restaurar vista guardada
  $(function () {
    try {
      var saved = localStorage.getItem('dh_vista');
      if (saved) dhSetVista(saved);
    } catch(e) {}
  });

  /* ═══════════════════════════════════════════════
     MODAL: AGREGAR TIPO DE PRODUCTO
  ═══════════════════════════════════════════════ */
  var $tipoModal;
  $(function () { $tipoModal = $('#dh-add-tipo-modal'); });

  window.dhShowAddTipoModal = function () {
    if (!$tipoModal || !$tipoModal.length) return;
    $tipoModal.css({ display:'flex', 'pointer-events':'auto' }).animate({ opacity:1 }, 150);
    setTimeout(function () { $('#dh-new-tipo-nombre').focus(); }, 160);
  };
  window.dhHideAddTipoModal = function () {
    if (!$tipoModal || !$tipoModal.length) return;
    $tipoModal.animate({ opacity:0 }, 150, function () { $(this).css({ display:'none', 'pointer-events':'none' }); });
  };
  $(document).on('keydown', function (e) { if (e.key === 'Escape') dhHideAddTipoModal(); });
  $(document).on('click', '#dh-add-tipo-modal', function (e) {
    if ($(e.target).is('#dh-add-tipo-modal')) dhHideAddTipoModal();
  });

  window.dhConfirmAddTipo = function () {
    var nombre = $('#dh-new-tipo-nombre').val().trim();
    if (!nombre) { $('#dh-new-tipo-nombre').css('border-color','#dc3232').focus(); return; }
    $('#dh-new-tipo-nombre').css('border-color','');
    $.post(dhAdmin.ajax_url,
      { action:'dh_add_tipo_producto', nonce:dhAdmin.nonce, nombre:nombre },
      function (res) {
        if (res.success) {
          window.location.href = dhAdmin.ajax_url.replace('admin-ajax.php','') + 'admin.php?page=dh-configuracion&tipo=' + res.data.slug;
        } else { alert((res.data && res.data.msg) || 'Error al crear el tipo.'); }
      }
    ).fail(function () { alert('Error de conexión.'); });
  };
  $(document).on('keydown', '#dh-new-tipo-nombre', function (e) {
    if (e.key === 'Enter') { e.preventDefault(); dhConfirmAddTipo(); }
  });

  /* ═══════════════════════════════════════════════
     ELIMINAR TIPO DE PRODUCTO
  ═══════════════════════════════════════════════ */
  window.dhDeleteTipo = function (slug, nombre) {
    if (!confirm('¿Eliminás el tipo "' + nombre + '"?')) return;
    $.post(dhAdmin.ajax_url,
      { action:'dh_delete_tipo_producto', nonce:dhAdmin.nonce, slug:slug },
      function (res) {
        if (res.success) { window.location.href = window.location.href.replace(/[?&]tipo=[^&]*/, ''); }
        else alert('Error al eliminar.');
      }
    ).fail(function () { alert('Error de conexión.'); });
  };

  /* ═══════════════════════════════════════════════
     VARIANTES SIMPLES (colores, tipos_lana, micras)
  ═══════════════════════════════════════════════ */
  window.dhAgregarTipoVariante = function (slug, campo) {
    var $input = $('#dh-new-variante-input');
    var valor  = $input.val().trim();
    if (!valor) { $input.css('border-color','#dc3232').focus(); return; }
    $input.css('border-color','');
    $.post(dhAdmin.ajax_url,
      { action:'dh_save_tipo_variante', nonce:dhAdmin.nonce, slug:slug, campo:campo, valor:valor },
      function (res) {
        if (res.success) { dhRenderVarianteList(slug, campo, res.data.tipo[campo]); $input.val('').focus(); }
        else alert((res.data && res.data.msg) || 'Error.');
      }
    ).fail(function () { alert('Error de conexión.'); });
  };
  $(document).on('keydown', '#dh-new-variante-input', function (e) {
    if (e.key !== 'Enter') return;
    e.preventDefault();
    var slugM  = (window.location.search.match(/[?&]tipo=([^&]+)/)     || [])[1] || '';
    var campoM = (window.location.search.match(/[?&]variante=([^&]+)/) || [])[1] || 'colores';
    dhAgregarTipoVariante(slugM, campoM);
  });

  /* ═══════════════════════════════════════════════
     MEDIDAS (con precios)
  ═══════════════════════════════════════════════ */
  window.dhAgregarMedida = function (slug) {
    var nombre = $('#dh-medida-nombre').val().trim();
    var pSena  = parseFloat($('#dh-medida-precio-sena').val())  || 0;
    var pTotal = parseFloat($('#dh-medida-precio-total').val()) || 0;
    if (!nombre) { $('#dh-medida-nombre').css('border-color','#dc3232').focus(); return; }
    $('#dh-medida-nombre').css('border-color','');
    $.post(dhAdmin.ajax_url,
      { action:'dh_save_tipo_variante', nonce:dhAdmin.nonce, slug:slug, campo:'medidas',
        nombre:nombre, precio_sena:pSena, precio_total:pTotal },
      function (res) {
        if (res.success) {
          dhRenderMedidasTable(slug, res.data.tipo['medidas']);
          $('#dh-medida-nombre, #dh-medida-precio-sena, #dh-medida-precio-total').val('');
        } else alert((res.data && res.data.msg) || 'Error.');
      }
    ).fail(function () { alert('Error de conexión.'); });
  };

  /* ═══════════════════════════════════════════════
     ELIMINAR VARIANTE / MEDIDA
  ═══════════════════════════════════════════════ */
  window.dhEliminarTipoVariante = function (slug, campo, index, btn) {
    if (!confirm('¿Eliminás esta opción?')) return;
    $(btn).closest(campo === 'medidas' ? 'tr' : '.dh-variante-item').css('opacity', .5);
    $.post(dhAdmin.ajax_url,
      { action:'dh_delete_tipo_variante', nonce:dhAdmin.nonce, slug:slug, campo:campo, index:index },
      function (res) {
        if (res.success) {
          if (campo === 'medidas') dhRenderMedidasTable(slug, res.data.tipo['medidas']);
          else dhRenderVarianteList(slug, campo, res.data.tipo[campo]);
        } else {
          $(btn).closest('tr, .dh-variante-item').css('opacity', 1);
          alert('Error al eliminar.');
        }
      }
    ).fail(function () { $(btn).closest('tr, .dh-variante-item').css('opacity', 1); alert('Error de conexión.'); });
  };

  function dhRenderVarianteList(slug, campo, lista) {
    var $list = $('#dh-variante-list-' + slug + '-' + campo);
    if (!$list.length) return;
    if (!lista || !lista.length) { $list.html('<div class="dh-variante-empty">Sin opciones configuradas.</div>'); return; }
    $list.html(lista.map(function (v, i) {
      return '<div class="dh-variante-item">'
        + '<span class="dh-variante-handle dashicons dashicons-menu"></span>'
        + '<span class="dh-variante-nombre">' + dhEsc(v) + '</span>'
        + '<button class="dh-variante-delete" onclick="dhEliminarTipoVariante(\'' + slug + '\',\'' + campo + '\',' + i + ',this)">'
        + '<span class="dashicons dashicons-trash"></span></button></div>';
    }).join(''));
    $('.dh-subtab[href*="variante=' + campo + '"] .dh-subtab-count').text(lista.length);
  }

  function dhRenderMedidasTable(slug, medidas) {
    var $tbody = $('#dh-medidas-table-' + slug + ' tbody');
    if (!$tbody.length) return;
    if (!medidas || !medidas.length) {
      $tbody.html('<tr class="dh-medidas-empty"><td colspan="4">Sin medidas configuradas.</td></tr>'); return;
    }
    $tbody.html(medidas.map(function (m, i) {
      return '<tr data-index="' + i + '">'
        + '<td><strong>' + dhEsc(m.nombre) + '</strong></td>'
        + '<td><span class="dh-price-tag">$' + dhFmtNum(m.precio_sena) + '</span></td>'
        + '<td><span class="dh-price-tag dh-price-total-tag">$' + dhFmtNum(m.precio_total) + '</span></td>'
        + '<td><button class="dh-variante-delete" onclick="dhEliminarTipoVariante(\'' + slug + '\',\'medidas\',' + i + ',this)">'
        + '<span class="dashicons dashicons-trash"></span></button></td></tr>';
    }).join(''));
    $('.dh-subtab[href*="variante=medidas"] .dh-subtab-count').text(medidas.length);
  }

  /* ═══════════════════════════════════════════════
     VALIDACIONES GENERALES
  ═══════════════════════════════════════════════ */
  $(function () {
    $(document).on('click', '.submitdelete', function (e) {
      if (!confirm('¿Eliminás este taller? No se puede deshacer.')) e.preventDefault();
    });

    $('form#post').on('submit', function (e) {
      if ($('body').hasClass('post-type-dh_taller') && !$('#dh_fecha').val()) {
        alert('Por favor ingresá la fecha del taller antes de guardar.');
        e.preventDefault();
        var firstTab = document.querySelector('.dh-meta-tab');
        if (firstTab) dhTab('general', firstTab);
        setTimeout(function () { $('#dh_fecha').css('border-color','#dc3232').focus(); }, 100);
      }
    });
    $('#dh_fecha').on('input', function () { $(this).css('border-color',''); });

    $('form.dh-manual-form').on('submit', function (e) {
      var ok = true;
      $(this).find('[required]').each(function () {
        if (!$(this).val()) { $(this).css('border-color','#dc3232'); ok = false; }
        else $(this).css('border-color','');
      });
      if (!ok) { e.preventDefault(); alert('Completá todos los campos obligatorios.'); }
    });
    $(document).on('input change', 'form.dh-manual-form [required]', function () {
      if ($(this).val()) $(this).css('border-color','');
    });

    // Spinner CSS
    $('<style>.spin{animation:dhSpin .7s linear infinite;display:inline-block;}@keyframes dhSpin{to{transform:rotate(360deg);}}</style>').appendTo('head');
  });

  /* ═══════════════════════════════════════════════
     ACCIONES INSCRIPCIONES
  ═══════════════════════════════════════════════ */

  // ── Modal de edición ────────────────────────
  var $editOverlay;
  $(function () { $editOverlay = $('#dh-edit-modal-overlay'); });

  window.dhOpenEditModal = function (id) {
    if (!$editOverlay || !$editOverlay.length) { alert('Modal no encontrado, recargá la página.'); return; }
    $('#dh-edit-modal-body').html('<div style="text-align:center;padding:40px;color:#999;"><span class="dashicons dashicons-update spin"></span> Cargando…</div>');
    $('#dh-edit-modal-id').text('#' + id);
    $editOverlay.css({ display:'flex', 'pointer-events':'auto' }).animate({ opacity:1 }, 150);

    $.post(dhAdmin.ajax_url,
      { action:'dh_get_inscripcion', nonce:dhAdmin.nonce, id:id },
      function (res) {
        if (!res.success) { alert(res.data.msg || 'Error.'); dhCloseEditModal(); return; }
        var d = res.data;

        var html = '<form id="dh-edit-form">'
          + '<div class="dh-edit-grid">'
          + '<div class="dh-edit-section-title">👤 Datos del alumno</div>'
          + '<div class="dh-form-group"><label>Nombre completo *</label><input type="text" name="nombre" value="' + dhEsc(d.nombre) + '" required></div>'
          + '<div class="dh-form-group"><label>Email *</label><input type="email" name="email" value="' + dhEsc(d.email) + '" required></div>'
          + '<div class="dh-form-group"><label>Teléfono</label><input type="tel" name="telefono" value="' + dhEsc(d.telefono || '') + '"></div>'
          + '<div class="dh-edit-section-title">📋 Inscripción</div>'
          + '<div class="dh-form-group"><label>Turno</label><select name="turno">'
          + '<option value="matutino"'  + (d.turno === 'matutino'   ? ' selected' : '') + '>☀️ Matutino</option>'
          + '<option value="vespertino"'+ (d.turno === 'vespertino' ? ' selected' : '') + '>🌇 Vespertino</option>'
          + '</select></div>'
          + '<div class="dh-form-group"><label>Tipo de pago</label><select name="tipo_pago">'
          + '<option value="sena"'     + (d.tipo_pago === 'sena'     ? ' selected' : '') + '>💰 Seña</option>'
          + '<option value="total"'    + (d.tipo_pago === 'total'    ? ' selected' : '') + '>✅ Total</option>'
          + '<option value="cortesia"' + (d.tipo_pago === 'cortesia' ? ' selected' : '') + '>🎁 Cortesía</option>'
          + '</select></div>'
          + '<div class="dh-form-group dh-edit-full"><label>Notas internas</label><textarea name="notas" rows="3">' + dhEsc(d.notas || '') + '</textarea></div>'
          + '</div>'
          + '<div id="dh-edit-error" class="dh-form-error" style="display:none;margin-top:12px;"></div>'
          + '<div style="display:flex;gap:10px;margin-top:20px;">'
          + '<button type="submit" class="dh-btn dh-btn-primary">💾 Guardar cambios</button>'
          + '<button type="button" class="dh-btn dh-btn-ghost" onclick="dhCloseEditModal()">Cancelar</button>'
          + '</div>'
          + '<input type="hidden" name="id" value="' + id + '">'
          + '</form>';

        $('#dh-edit-modal-body').html(html);

        $('#dh-edit-form').on('submit', function (e) {
          e.preventDefault();
          var $btn = $(this).find('[type=submit]');
          $btn.prop('disabled', true).text('Guardando…');
          $('#dh-edit-error').hide();
          $.post(dhAdmin.ajax_url,
            $.extend({ action:'dh_editar_inscripcion', nonce:dhAdmin.nonce }, $(this).serialize()),
            function (r) {
              if (r.success) { dhCloseEditModal(); location.reload(); }
              else { $('#dh-edit-error').text(r.data.msg || 'Error.').show(); $btn.prop('disabled', false).html('💾 Guardar cambios'); }
            }
          ).fail(function () { $('#dh-edit-error').text('Error de conexión.').show(); $btn.prop('disabled', false).html('💾 Guardar cambios'); });
        });
      }
    ).fail(function () { alert('Error de conexión.'); dhCloseEditModal(); });
  };

  window.dhCloseEditModal = function () {
    if (!$editOverlay || !$editOverlay.length) return;
    $editOverlay.animate({ opacity:0 }, 150, function () { $(this).css({ display:'none', 'pointer-events':'none' }); });
  };
  $(document).on('keydown', function (e) { if (e.key === 'Escape') dhCloseEditModal(); });
  $(document).on('click', '#dh-edit-modal-overlay', function (e) {
    if ($(e.target).is('#dh-edit-modal-overlay')) dhCloseEditModal();
  });

  // ── Cambiar estado ──────────────────────────
  window.dhCambiarEstado = function (id, estado) {
    var msgs = { confirmado:'¿Confirmar esta inscripción?', cancelado:'¿Cancelar esta inscripción?' };
    if (!confirm(msgs[estado] || '¿Continuar?')) return;
    $.post(dhAdmin.ajax_url,
      { action:'dh_estado_inscripcion', nonce:dhAdmin.nonce, id:id, estado:estado },
      function (res) {
        if (res.success) location.reload();
        else alert(res.data.msg || 'Error.');
      }
    ).fail(function () { alert('Error de conexión.'); });
  };

  // ── Eliminar inscripción ────────────────────
  window.dhEliminarInscripcion = function (id) {
    if (!confirm('¿Eliminar este registro? Se repondrá el cupo y se cancelará el pedido WooCommerce si existe.')) return;
    $.post(dhAdmin.ajax_url,
      { action:'dh_eliminar_inscripcion', nonce:dhAdmin.nonce, id:id },
      function (res) {
        if (res.success) { $('#dh-insc-row-' + id).fadeOut(300, function () { $(this).remove(); }); }
        else alert(res.data.msg || 'Error.');
      }
    ).fail(function () { alert('Error de conexión.'); });
  };

  /* ═══════════════════════════════════════════════
     ACCIONES TALLERES
  ═══════════════════════════════════════════════ */
  window.dhEliminarTallerDashboard = function (id, nombre) {
    if (!confirm('¿Eliminás el taller "' + nombre + '"? El taller pasará a la papelera de WordPress.')) return;
    $.post(dhAdmin.ajax_url,
      { action:'dh_eliminar_taller', nonce:dhAdmin.nonce, id:id },
      function (res) {
        if (res.success) { $('#dh-taller-card-' + id).fadeOut(350, function () { $(this).remove(); }); }
        else alert(res.data.msg || 'Error.');
      }
    ).fail(function () { alert('Error de conexión.'); });
  };

  window.dhDuplicarTaller = function (id) {
    if (!confirm('¿Duplicar este taller? Se creará un borrador con la misma información (sin inscriptos).')) return;
    $.post(dhAdmin.ajax_url,
      { action:'dh_duplicar_taller', nonce:dhAdmin.nonce, id:id },
      function (res) {
        if (res.success) {
          if (confirm('Taller duplicado como borrador. ¿Editarlo ahora?')) { window.location.href = res.data.edit_url; }
          else location.reload();
        } else alert(res.data.msg || 'Error.');
      }
    ).fail(function () { alert('Error de conexión.'); });
  };

  /* ═══════════════════════════════════════════════
     GRÁFICA DE INSCRIPCIONES (v1.5)
  ═══════════════════════════════════════════════ */
  $(function () {
    var $canvas = $('#dh-chart-inscripciones');
    if (!$canvas.length) return;

    var confirmados = parseInt($canvas.data('confirmados')) || 0;
    var pendientes  = parseInt($canvas.data('pendientes'))  || 0;
    var cancelados  = parseInt($canvas.data('cancelados'))  || 0;
    var total       = confirmados + pendientes + cancelados;

    if (total === 0) { $canvas.closest('.dh-chart-wrap').hide(); return; }

    var canvas  = $canvas[0];
    var ctx     = canvas.getContext('2d');
    var size    = 180;
    canvas.width  = size;
    canvas.height = size;

    var cx = size / 2, cy = size / 2, r = 76, ri = 44;
    var data   = [confirmados, pendientes, cancelados];
    var colors = ['#3a9966', '#d97706', '#c0392b'];
    var start  = -Math.PI / 2;

    data.forEach(function (val, idx) {
      if (!val) return;
      var slice = (val / total) * 2 * Math.PI;
      ctx.beginPath();
      ctx.moveTo(cx, cy);
      ctx.arc(cx, cy, r, start, start + slice);
      ctx.closePath();
      ctx.fillStyle = colors[idx];
      ctx.fill();
      start += slice;
    });

    // Donut hole
    ctx.beginPath();
    ctx.arc(cx, cy, ri, 0, 2 * Math.PI);
    ctx.fillStyle = '#fff';
    ctx.fill();

    // Número central
    ctx.fillStyle = '#2c2020';
    ctx.font = 'bold 22px -apple-system,sans-serif';
    ctx.textAlign = 'center';
    ctx.textBaseline = 'middle';
    ctx.fillText(total, cx, cy);
  });

})(jQuery);
