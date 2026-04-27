/**
 * DH Talleres — Admin JS v1.6
 * BUG FIX: modal de edición usa string serializado puro (evita mezcla objeto+string)
 * NEW: selector de taller y materiales en modal edición
 * NEW: botón reenviar email con confirmación
 * NEW: gráfico Seña/Total/Cortesía
 * NEW: papelera — restaurar / purgar inscripciones y talleres
 */
(function ($) {
  'use strict';

  /* ═══════════════════════════════════════════════
     HELPERS GLOBALES
  ═══════════════════════════════════════════════ */
  function dhEsc(s) {
    return String(s || '').replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;');
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
      tmp.value = text; document.body.appendChild(tmp); tmp.select();
      document.execCommand('copy'); document.body.removeChild(tmp);
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
      $wrap.html('<div style="margin-top:8px;"><a href="' + url + '" target="_blank" class="button button-small">🗺️ Ver en Google Maps</a><br><small style="color:#888;font-size:11px;margin-top:4px;display:block;">Para embed, usá la URL de "Compartir → Insertar mapa".</small></div>').show();
    }
  }
  $(document).on('input change', '#dh_maps_url', function () { dhPreviewMaps($(this).val()); });
  $(function () { if ($('#dh_maps_url').length && $('#dh_maps_url').val()) dhPreviewMaps($('#dh_maps_url').val()); });

  /* ═══════════════════════════════════════════════
     DASHBOARD: ORDENAR Y VISTA
  ═══════════════════════════════════════════════ */
  window.dhOrdenar = function (criterio) {
    var $grid  = $('#dh-talleres-grid');
    var $cards = $grid.find('.dh-taller-card').toArray();
    $cards.sort(function (a, b) {
      var va = $(a).data(criterio) || '';
      var vb = $(b).data(criterio) || '';
      if (criterio === 'fecha') return new Date(va || '9999') - new Date(vb || '9999');
      return String(va).localeCompare(String(vb), 'es');
    });
    $cards.forEach(function (c) { $grid.append(c); });
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
  $(function () {
    try { var saved = localStorage.getItem('dh_vista'); if (saved) dhSetVista(saved); } catch(e) {}
  });

  /* ═══════════════════════════════════════════════
     MODAL: TIPO DE PRODUCTO
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
    if (slugM) dhAgregarTipoVariante(slugM, campoM);
  });

  window.dhEliminarVariante = function (slug, campo, valor) {
    if (!confirm('¿Eliminar "' + valor + '"?')) return;
    $.post(dhAdmin.ajax_url,
      { action:'dh_delete_tipo_variante', nonce:dhAdmin.nonce, slug:slug, campo:campo, valor:valor },
      function (res) {
        if (res.success) dhRenderVarianteList(slug, campo, res.data.tipo[campo]);
        else alert('Error al eliminar.');
      }
    ).fail(function () { alert('Error de conexión.'); });
  };

  function dhRenderVarianteList(slug, campo, items) {
    var $list = $('#dh-variante-list');
    if (!$list.length) return;
    if (!items || !items.length) { $list.html('<span style="color:#aaa;font-size:13px;">Sin opciones todavía.</span>'); return; }
    var html = '<div class="dh-variante-tags">';
    items.forEach(function (v) {
      html += '<span class="dh-variante-tag">' + dhEsc(v)
           + '<button type="button" class="dh-var-del-btn" onclick="dhEliminarVariante(\'' + dhEsc(slug) + '\',\'' + dhEsc(campo) + '\',\'' + dhEsc(v) + '\')" title="Eliminar">✕</button></span>';
    });
    html += '</div>';
    $list.html(html);
  }

  window.dhAgregarMedida = function (slug) {
    var nombre = $('#dh-medida-nombre').val().trim();
    var precio = $('#dh-medida-precio').val().trim();
    if (!nombre) { $('#dh-medida-nombre').css('border-color','#dc3232').focus(); return; }
    $('#dh-medida-nombre,#dh-medida-precio').css('border-color','');
    $.post(dhAdmin.ajax_url,
      { action:'dh_save_medida', nonce:dhAdmin.nonce, slug:slug, nombre:nombre, precio:precio },
      function (res) {
        if (res.success) {
          dhRenderMedidaList(slug, res.data.tipo.medidas);
          $('#dh-medida-nombre').val(''); $('#dh-medida-precio').val('');
          $('#dh-medida-nombre').focus();
        } else alert((res.data && res.data.msg) || 'Error.');
      }
    ).fail(function () { alert('Error de conexión.'); });
  };

  window.dhEliminarMedida = function (slug, nombre) {
    if (!confirm('¿Eliminar medida "' + nombre + '"?')) return;
    $.post(dhAdmin.ajax_url,
      { action:'dh_delete_medida', nonce:dhAdmin.nonce, slug:slug, nombre:nombre },
      function (res) {
        if (res.success) dhRenderMedidaList(slug, res.data.tipo.medidas);
        else alert('Error al eliminar.');
      }
    ).fail(function () { alert('Error de conexión.'); });
  };

  function dhRenderMedidaList(slug, medidas) {
    var $list = $('#dh-medidas-list');
    if (!$list.length) return;
    if (!medidas || !medidas.length) { $list.html('<span style="color:#aaa;font-size:13px;">Sin medidas todavía.</span>'); return; }
    var html = '<table style="width:100%;border-collapse:collapse;font-size:13px;">'
             + '<thead><tr><th style="text-align:left;padding:4px 8px;border-bottom:1px solid #ddd;color:#666;">Medida</th>'
             + '<th style="text-align:left;padding:4px 8px;border-bottom:1px solid #ddd;color:#666;">Precio extra</th><th></th></tr></thead><tbody>';
    medidas.forEach(function (m) {
      html += '<tr>'
            + '<td style="padding:6px 8px;">' + dhEsc(m.nombre) + '</td>'
            + '<td style="padding:6px 8px;">' + (m.precio ? '+$' + dhEsc(String(m.precio)) : '—') + '</td>'
            + '<td style="padding:6px 8px;"><button type="button" class="dh-var-del-btn" onclick="dhEliminarMedida(\'' + dhEsc(slug) + '\',\'' + dhEsc(m.nombre) + '\')">✕</button></td>'
            + '</tr>';
    });
    html += '</tbody></table>';
    $list.html(html);
  }

  /* ═══════════════════════════════════════════════
     ACCIONES DE INSCRIPCIONES
  ═══════════════════════════════════════════════ */

  // ── Modal de edición ────────────────────────────
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
        var d            = res.data;
        var talleres     = d.talleres     || [];
        var tiposMap     = d.taller_tipos_map || {};
        var tiposProducto= d.tipos_producto  || (dhAdmin.tipos || []);

        // ── Select de talleres ──────────────────────
        var tallerOpts = '<option value="">— Seleccioná —</option>';
        talleres.forEach(function (t) {
          tallerOpts += '<option value="' + t.id + '"' + (t.id == d.taller_id ? ' selected' : '') + '>' + dhEsc(t.title) + '</option>';
        });

        // ── Opciones iniciales de variantes según taller actual ──
        function optsFor(campo, current) {
          var tipoSlug = tiposMap[d.taller_id] || '';
          var tipo = tiposProducto.find ? tiposProducto.find(function(t){ return t.slug === tipoSlug; }) : null;
          if (!tipo && tiposProducto.length) tipo = tiposProducto[0];
          var lista = (tipo && tipo[campo]) ? tipo[campo] : [];
          var out = '<option value="">— Sin especificar —</option>';
          lista.forEach(function(item){
            var val = typeof item === 'object' ? item.nombre : item;
            out += '<option value="' + dhEsc(val) + '"' + (val === current ? ' selected' : '') + '>' + dhEsc(val) + '</option>';
          });
          return out;
        }

        var vv       = d.variantes || {};
        var html = '<form id="dh-edit-form">'
          + '<div class="dh-edit-grid">'

          // ── Taller ──────────────────────────────────
          + '<div class="dh-edit-section-title" style="grid-column:1/-1">📋 Inscripción</div>'
          + '<div class="dh-form-group"><label>Taller</label>'
          + '<select name="taller_id" id="dh-edit-taller-sel">' + tallerOpts + '</select></div>'

          + '<div class="dh-form-group"><label>Turno</label><select name="turno">'
          + '<option value="matutino"'  + (d.turno === 'matutino'   ? ' selected' : '') + '>☀️ Matutino</option>'
          + '<option value="vespertino"'+ (d.turno === 'vespertino' ? ' selected' : '') + '>🌇 Vespertino</option>'
          + '</select></div>'

          + '<div class="dh-form-group"><label>Tipo de pago</label><select name="tipo_pago">'
          + '<option value="sena"'     + (d.tipo_pago === 'sena'     ? ' selected' : '') + '>💰 Seña</option>'
          + '<option value="total"'    + (d.tipo_pago === 'total'    ? ' selected' : '') + '>✅ Total</option>'
          + '<option value="cortesia"' + (d.tipo_pago === 'cortesia' ? ' selected' : '') + '>🎁 Cortesía</option>'
          + '</select></div>'

          // ── Alumno ──────────────────────────────────
          + '<div class="dh-edit-section-title" style="grid-column:1/-1">👤 Datos del alumno</div>'
          + '<div class="dh-form-group"><label>Nombre completo *</label><input type="text" name="nombre" value="' + dhEsc(d.nombre) + '" required></div>'
          + '<div class="dh-form-group"><label>Email *</label><input type="email" name="email" value="' + dhEsc(d.email) + '" required></div>'
          + '<div class="dh-form-group"><label>Teléfono</label><input type="tel" name="telefono" value="' + dhEsc(d.telefono || '') + '"></div>'

          // ── Material ────────────────────────────────
          + '<div class="dh-edit-section-title" style="grid-column:1/-1">🎨 Material</div>'
          + '<div class="dh-form-group"><label>🎨 Color</label><select name="variante_color" id="dh-edit-var-color">' + optsFor('colores', vv.color||'') + '</select></div>'
          + '<div class="dh-form-group"><label>🧶 Tipo de lana</label><select name="variante_tipo_lana" id="dh-edit-var-tipo_lana">' + optsFor('tipos_lana', vv.tipo_lana||'') + '</select></div>'
          + '<div class="dh-form-group"><label>🔬 Micras</label><select name="variante_micras" id="dh-edit-var-micras">' + optsFor('micras', vv.micras||'') + '</select></div>'
          + '<div class="dh-form-group"><label>📏 Medida</label><select name="variante_medida" id="dh-edit-var-medida">' + optsFor('medidas', vv.medida||'') + '</select></div>'

          // ── Notas ───────────────────────────────────
          + '<div class="dh-form-group dh-edit-full"><label>📝 Notas internas</label><textarea name="notas" rows="3">' + dhEsc(d.notas || '') + '</textarea></div>'

          + '</div>'
          + '<div id="dh-edit-error" class="dh-form-error" style="display:none;margin-top:12px;"></div>'
          + '<div style="display:flex;gap:10px;margin-top:20px;">'
          + '<button type="submit" class="dh-btn dh-btn-primary" id="dh-edit-save-btn">💾 Guardar cambios</button>'
          + '<button type="button" class="dh-btn dh-btn-ghost" onclick="dhCloseEditModal()">Cancelar</button>'
          + '</div>'
          + '<input type="hidden" name="id" value="' + id + '">'
          + '</form>';

        $('#dh-edit-modal-body').html(html);

        // ── Recarga de variantes al cambiar taller ──
        $('#dh-edit-taller-sel').on('change', function () {
          var tid = $(this).val();
          var tipoSlug = tiposMap[tid] || '';
          var tipo = tiposProducto.find ? tiposProducto.find(function(t){ return t.slug===tipoSlug; }) : null;
          if (!tipo && tiposProducto.length) tipo = tiposProducto[0];
          var campoMap = { color:'colores', tipo_lana:'tipos_lana', micras:'micras', medida:'medidas' };
          Object.keys(campoMap).forEach(function(k){
            var $sel = $('#dh-edit-var-' + k);
            var lista = (tipo && tipo[campoMap[k]]) ? tipo[campoMap[k]] : [];
            var opts = '<option value="">— Sin especificar —</option>';
            lista.forEach(function(item){
              var val = typeof item==='object' ? item.nombre : item;
              opts += '<option value="' + dhEsc(val) + '">' + dhEsc(val) + '</option>';
            });
            $sel.html(opts);
          });
        });

        // ── Submit — BUG FIX: envío como string serializado puro ──
        $('#dh-edit-form').on('submit', function (e) {
          e.preventDefault();
          var $btn = $('#dh-edit-save-btn');
          $btn.prop('disabled', true).text('Guardando…');
          $('#dh-edit-error').hide();

          // Serializar el form como string y agregar action+nonce al final
          var params = $(this).serialize()
                     + '&action=dh_editar_inscripcion'
                     + '&nonce=' + encodeURIComponent(dhAdmin.nonce);

          $.post(dhAdmin.ajax_url, params, function (r) {
            if (r.success) { dhCloseEditModal(); location.reload(); }
            else {
              $('#dh-edit-error').text(r.data && r.data.msg ? r.data.msg : 'Error al guardar.').show();
              $btn.prop('disabled', false).html('💾 Guardar cambios');
            }
          }).fail(function () {
            $('#dh-edit-error').text('Error de conexión.').show();
            $btn.prop('disabled', false).html('💾 Guardar cambios');
          });
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

  // ── Reenviar email ──────────────────────────────
  window.dhReenviarEmail = function (id, email) {
    if (!confirm('¿Reenviar el email de inscripción a ' + email + '?\n\nEl alumno recibirá nuevamente la confirmación con los datos de su inscripción.')) return;
    $.post(dhAdmin.ajax_url,
      { action:'dh_reenviar_email', nonce:dhAdmin.nonce, id:id },
      function (res) {
        if (res.success) alert('✅ ' + (res.data.msg || 'Email reenviado correctamente.'));
        else alert('❌ ' + (res.data && res.data.msg ? res.data.msg : 'Error al reenviar.'));
      }
    ).fail(function () { alert('Error de conexión.'); });
  };

  // ── Cambiar estado ───────────────────────────────
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

  // ── Eliminar inscripción (soft delete) ───────────
  window.dhEliminarInscripcion = function (id) {
    if (!confirm('¿Mover este registro a la papelera?\n\nSe repondrá el cupo y se cancelará el pedido WooCommerce si existe.\nPodrás restaurarlo desde la sección Papelera.')) return;
    $.post(dhAdmin.ajax_url,
      { action:'dh_eliminar_inscripcion', nonce:dhAdmin.nonce, id:id },
      function (res) {
        if (res.success) { $('#dh-insc-row-' + id).fadeOut(300, function () { $(this).remove(); }); }
        else alert(res.data.msg || 'Error.');
      }
    ).fail(function () { alert('Error de conexión.'); });
  };

  /* ═══════════════════════════════════════════════
     PAPELERA — INSCRIPCIONES
  ═══════════════════════════════════════════════ */
  window.dhRestaurarInscripcion = function (id) {
    if (!confirm('¿Restaurar esta inscripción?\n\nVolverá a la lista de alumnos con estado "Cancelado". Podés reactivarla desde allí.')) return;
    $.post(dhAdmin.ajax_url,
      { action:'dh_restaurar_inscripcion', nonce:dhAdmin.nonce, id:id },
      function (res) {
        if (res.success) { alert('✅ ' + res.data.msg); $('#dh-trash-insc-' + id).fadeOut(300, function(){ $(this).remove(); }); }
        else alert(res.data.msg || 'Error.');
      }
    ).fail(function () { alert('Error de conexión.'); });
  };

  window.dhPurgarInscripcion = function (id) {
    if (!confirm('⚠️ ¿Eliminar DEFINITIVAMENTE esta inscripción?\n\nEsta acción no se puede deshacer.')) return;
    $.post(dhAdmin.ajax_url,
      { action:'dh_purgar_inscripcion', nonce:dhAdmin.nonce, id:id },
      function (res) {
        if (res.success) { $('#dh-trash-insc-' + id).fadeOut(300, function(){ $(this).remove(); }); }
        else alert(res.data.msg || 'Error.');
      }
    ).fail(function () { alert('Error de conexión.'); });
  };

  /* ═══════════════════════════════════════════════
     ACCIONES TALLERES
  ═══════════════════════════════════════════════ */
  window.dhEliminarTallerDashboard = function (id, nombre) {
    if (!confirm('¿Mover el taller "' + nombre + '" a la papelera?\n\nPodrás restaurarlo desde la sección Papelera.')) return;
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
     PAPELERA — TALLERES
  ═══════════════════════════════════════════════ */
  window.dhRestaurarTaller = function (id) {
    if (!confirm('¿Restaurar este taller?')) return;
    $.post(dhAdmin.ajax_url,
      { action:'dh_restaurar_taller', nonce:dhAdmin.nonce, id:id },
      function (res) {
        if (res.success) { alert('✅ Taller restaurado.'); $('#dh-trash-taller-' + id).fadeOut(300, function(){ $(this).remove(); }); }
        else alert(res.data.msg || 'Error.');
      }
    ).fail(function () { alert('Error de conexión.'); });
  };

  /* ═══════════════════════════════════════════════
     GRÁFICAS DE INSCRIPCIONES (v1.5 + v1.6)
  ═══════════════════════════════════════════════ */
  function dhDrawDonut(canvas, data, colors) {
    var ctx   = canvas.getContext('2d');
    var size  = 180;
    canvas.width = size; canvas.height = size;
    var cx = size/2, cy = size/2, r = 76, ri = 44;
    var total = data.reduce(function(s,v){ return s+v; }, 0);
    if (total === 0) return;
    var start = -Math.PI / 2;
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
    ctx.beginPath(); ctx.arc(cx, cy, ri, 0, 2*Math.PI);
    ctx.fillStyle = '#fff'; ctx.fill();
    // Número central
    ctx.fillStyle = '#2c2020';
    ctx.font = 'bold 22px -apple-system,sans-serif';
    ctx.textAlign = 'center'; ctx.textBaseline = 'middle';
    ctx.fillText(total, cx, cy);
  }

  $(function () {
    // Gráfico 1 — Distribución por estado
    var $c1 = $('#dh-chart-inscripciones');
    if ($c1.length) {
      var conf  = parseInt($c1.data('confirmados')) || 0;
      var pend  = parseInt($c1.data('pendientes'))  || 0;
      var canc  = parseInt($c1.data('cancelados'))  || 0;
      if (conf + pend + canc > 0) {
        dhDrawDonut($c1[0], [conf, pend, canc], ['#3a9966','#d97706','#c0392b']);
      } else { $c1.closest('.dh-chart-wrap').hide(); }
    }

    // Gráfico 2 — Distribución por tipo de pago
    var $c2 = $('#dh-chart-pagos');
    if ($c2.length) {
      var sena     = parseInt($c2.data('sena'))     || 0;
      var total    = parseInt($c2.data('total'))    || 0;
      var cortesia = parseInt($c2.data('cortesia')) || 0;
      if (sena + total + cortesia > 0) {
        dhDrawDonut($c2[0], [sena, total, cortesia], ['#8B5E3C','#2271b1','#6d3fc0']);
      }
    }
  });

})(jQuery);
