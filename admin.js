(function(){
  const $ = s => document.querySelector(s);
  const $$ = s => document.querySelectorAll(s);
  const csrf = $('meta[name="csrf-token"]')?.getAttribute('content') || '';

  // Estado global
  let especialidades = [];
  let medicosData = [];
  let secretariasData = [];
  let selectedTurnoId = null;
  let currentMedicoId = null;

  // Elementos DOM
  const createMedicoForm = $('#createMedicoForm');
  const createSecretariaForm = $('#createSecretariaForm');
  const tblMedicos = $('#tblMedicos');
  const tblSecretarias = $('#tblSecretarias');
  const tblAgendaBody = $('#tblAgenda tbody');
  const noData = $('#noData');
  
  // Mensajes
  const msgCreateMed = $('#msgCreateMed');
  const msgCreateSec = $('#msgCreateSec');
  const msgTurns = $('#msgTurns');
  const msgModal = $('#msgModal');

  // Filtros turnos
  const fEsp = $('#fEsp');
  const fMed = $('#fMed');
  const fFrom = $('#fFrom');
  const fTo = $('#fTo');
  const btnRefresh = $('#btnRefresh');
  const btnClearDates = $('#btnClearDates');
  const btnNewTurno = $('#btnNewTurno');

  // Reprogramación
  const reprogSection = $('#reprogSection');
  const newDate = $('#newDate');
  const newTime = $('#newTime');
  const btnReprog = $('#btnReprog');
  const btnCancelReprog = $('#btnCancelReprog');

  // Modales
  const modalCreateTurno = $('#modalCreateTurno');
  const formCreateTurno = $('#formCreateTurno');
  const searchPaciente = $('#searchPaciente');
  const pacienteResults = $('#pacienteResults');
  const selectedPacienteId = $('#selectedPacienteId');
  const selectedPacienteInfo = $('#selectedPacienteInfo');
  const turnoDate = $('#turnoDate');
  const turnoTime = $('#turnoTime');
  const btnCloseModal = $('#btnCloseModal');

  const modalEditMedico = $('#modalEditMedico');
  const formEditMedico = $('#formEditMedico');
  const btnCloseMedicoModal = $('#btnCloseMedicoModal');

  const modalEditSecretaria = $('#modalEditSecretaria');
  const formEditSecretaria = $('#formEditSecretaria');
  const btnCloseSecretariaModal = $('#btnCloseSecretariaModal');

  // Utilidades
  function esc(s){ return String(s??'').replace(/[&<>"']/g,c=>({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c])); }
  function setMsg(el, t, ok=true){ 
    if(!el) return; 
    el.textContent=t||''; 
    el.classList.remove('ok','err'); 
    el.classList.add(ok?'ok':'err'); 
  }
  function showModal(modal){ modal.style.display='flex'; }
  function hideModal(modal){ modal.style.display='none'; }

  // Tabs
  $$('.tab').forEach(t=>{
    t.addEventListener('click', ()=>{
      $$('section.card').forEach(sec=>sec.classList.add('hidden'));
      $$('.tab').forEach(x=>x.classList.remove('active'));
      t.classList.add('active');
      $('#tab-'+t.dataset.tab).classList.remove('hidden');
    });
  });

  // ========== CARGA INICIAL ==========
  async function loadInit(){
    try {
      const res = await fetch('admin.php?fetch=init', { headers:{ 'Accept':'application/json' }});
      const data = await res.json();
      if (!data.ok) { setMsg(msgCreateMed, 'Error cargando datos', false); return; }

      especialidades = data.especialidades || [];
      medicosData = data.medicos || [];
      secretariasData = data.secretarias || [];

      // Llenar selects de especialidad
      const espSelects = ['#espCreateSelect', '#fEsp', '#editMedEsp'];
      espSelects.forEach(sel => {
        const element = $(sel);
        if (element) {
          element.innerHTML = `<option value="">Elegir…</option>`;
          especialidades.forEach(e=>{
            const opt = document.createElement('option');
            opt.value=e.Id_Especialidad; opt.textContent=e.Nombre;
            element.appendChild(opt);
          });
        }
      });

      renderMedicos(medicosData);
      renderSecretarias(secretariasData);
    } catch (e) {
      console.error('Error en loadInit', e);
      setMsg(msgCreateMed, 'Error cargando inicial', false);
    }
  }

  // ========== MÉDICOS ==========
  function renderMedicos(rows){
    if(!tblMedicos) return;
    tblMedicos.innerHTML='';
    rows.forEach(r=>{
      const tr=document.createElement('tr');
      const dias = (r.Dias_Disponibles || 'N/A').split(',').map(d => d.charAt(0).toUpperCase()).join(',');
      const horario = `${r.Hora_Inicio?.substring(0,5) || '08:00'} - ${r.Hora_Fin?.substring(0,5) || '16:00'}`;
      tr.innerHTML = `
        <td>${esc((r.Apellido||'')+', '+(r.Nombre||''))}</td>
        <td>${esc(r.dni||'')}</td>
        <td>${esc(r.Especialidad||'')}</td>
        <td>${esc(r.Legajo||'')}</td>
        <td>${horario}</td>
        <td>${dias}</td>
        <td class="row-actions">
          <button class="btn ghost btn-edit-med" data-id="${r.Id_medico}">✏️ Editar</button>
          <button class="btn danger btn-delete-med" data-id="${r.Id_medico}">🗑️</button>
        </td>`;
      tblMedicos.appendChild(tr);
    });

    // Eventos
    $$('.btn-edit-med').forEach(b=>b.addEventListener('click', ()=> openEditMedico(b.dataset.id)));
    $$('.btn-delete-med').forEach(b=>b.addEventListener('click', ()=> deleteMedico(b.dataset.id)));
  }

  // Crear médico
  if (createMedicoForm) {
    createMedicoForm.addEventListener('submit', async (ev)=>{
      ev.preventDefault();
      setMsg(msgCreateMed, '');
      try{
        const fd = new FormData(createMedicoForm);
        const checkboxes = Array.from(createMedicoForm.querySelectorAll('input[name="dias_chk"]:checked')).map(i=>i.value);
        const diasCapitalizados = checkboxes.map(v => v.charAt(0).toUpperCase() + v.slice(1));
        fd.set('dias', diasCapitalizados.join(','));
        fd.set('action','create_medico');
        fd.set('csrf_token', csrf);

        const res = await fetch('admin.php', { method:'POST', body:fd, headers:{ 'Accept':'application/json' }});
        const data = await res.json();
        if (!data.ok) throw new Error(data.error || 'Error creando médico');
        setMsg(msgCreateMed, data.msg || 'Médico creado', true);
        createMedicoForm.reset();
        await loadInit();
      }catch(err){
        setMsg(msgCreateMed, err.message || 'Error', false);
      }
    });
  }

  // Editar médico
  function openEditMedico(id){
    const medico = medicosData.find(m => m.Id_medico == id);
    if (!medico) return;

    $('#editMedId').value = medico.Id_medico;
    $('#editMedNombre').value = medico.Nombre || '';
    $('#editMedApellido').value = medico.Apellido || '';
    $('#editMedEmail').value = medico.email || '';
    $('#editMedLegajo').value = medico.Legajo || '';
    $('#editMedEsp').value = medico.Id_Especialidad || '';
    $('#editMedHoraInicio').value = (medico.Hora_Inicio || '08:00:00').substring(0,5);
    $('#editMedHoraFin').value = (medico.Hora_Fin || '16:00:00').substring(0,5);

    // Días
    $$('.editDias').forEach(chk => chk.checked = false);
    const dias = (medico.Dias_Disponibles || '').toLowerCase().split(',');
    $$('.editDias').forEach(chk => {
      if (dias.includes(chk.value.toLowerCase())) chk.checked = true;
    });

    showModal(modalEditMedico);
  }

  if (formEditMedico) {
    formEditMedico.addEventListener('submit', async (ev)=>{
      ev.preventDefault();
      const msgEl = $('#msgMedicoModal');
      setMsg(msgEl, '');
      try{
        const fd = new FormData();
        fd.append('action', 'update_medico');
        fd.append('csrf_token', csrf);
        fd.append('id_medico', $('#editMedId').value);
        fd.append('nombre', $('#editMedNombre').value);
        fd.append('apellido', $('#editMedApellido').value);
        fd.append('email', $('#editMedEmail').value);
        fd.append('legajo', $('#editMedLegajo').value);
        fd.append('especialidad', $('#editMedEsp').value);
        fd.append('hora_inicio', $('#editMedHoraInicio').value);
        fd.append('hora_fin', $('#editMedHoraFin').value);

        const diasChecked = Array.from($$('.editDias:checked')).map(c=>c.value);
        fd.append('dias', diasChecked.join(','));

        const res = await fetch('admin.php', { method:'POST', body:fd, headers:{ 'Accept':'application/json' }});
        const data = await res.json();
        if (!data.ok) throw new Error(data.error || 'Error actualizando');
        
        setMsg(msgEl, data.msg || 'Médico actualizado', true);
        setTimeout(()=>{ hideModal(modalEditMedico); loadInit(); }, 1000);
      }catch(err){
        setMsg(msgEl, err.message, false);
      }
    });
  }

  // Eliminar médico
  async function deleteMedico(id){
    if (!confirm('¿Eliminar este médico? Esta acción no se puede deshacer.')) return;
    try{
      const fd = new FormData();
      fd.append('action', 'delete_medico');
      fd.append('id_medico', id);
      fd.append('csrf_token', csrf);

      const res = await fetch('admin.php', { method:'POST', body:fd, headers:{ 'Accept':'application/json' }});
      const data = await res.json();
      if (!data.ok) throw new Error(data.error || 'Error eliminando');
      
      setMsg(msgCreateMed, data.msg || 'Médico eliminado', true);
      await loadInit();
    }catch(err){
      setMsg(msgCreateMed, err.message, false);
    }
  }

  // ========== SECRETARIAS ==========
  function renderSecretarias(rows){
    if(!tblSecretarias) return;
    tblSecretarias.innerHTML='';
    rows.forEach(r=>{
      const tr=document.createElement('tr');
      tr.innerHTML = `
        <td>${esc((r.Apellido||'')+', '+(r.Nombre||''))}</td>
        <td>${esc(r.dni||'')}</td>
        <td>${esc(r.email||'')}</td>
        <td class="row-actions">
          <button class="btn ghost btn-edit-sec" data-id="${r.Id_secretaria}">✏️ Editar</button>
          <button class="btn danger btn-delete-sec" data-id="${r.Id_secretaria}">🗑️</button>
        </td>`;
      tblSecretarias.appendChild(tr);
    });

    // Eventos
    $('.btn-edit-sec').forEach(b=>b.addEventListener('click', ()=> openEditSecretaria(b.dataset.id)));
    $('.btn-delete-sec').forEach(b=>b.addEventListener('click', ()=> deleteSecretaria(b.dataset.id)));
  }

  // Crear secretaria
  if (createSecretariaForm) {
    createSecretariaForm.addEventListener('submit', async (e) => {
      e.preventDefault();
      setMsg(msgCreateSec, '');

      const fd = new FormData(createSecretariaForm);
      fd.append('action', 'create_secretaria');
      fd.append('csrf_token', csrf);

      try {
        const res = await fetch('admin.php', { method: 'POST', body: fd, headers:{ 'Accept':'application/json' }});
        const data = await res.json();
        if (!data.ok) throw new Error(data.error || 'No se pudo crear');
        setMsg(msgCreateSec, data.msg || 'Secretaria creada', true);
        createSecretariaForm.reset();
        await loadInit();
      } catch (err) {
        setMsg(msgCreateSec, err.message, false);
      }
    });
  }

  // Editar secretaria
  function openEditSecretaria(id){
    const sec = secretariasData.find(s => s.Id_secretaria == id);
    if (!sec) return;

    $('#editSecId').value = sec.Id_secretaria;
    $('#editSecNombre').value = sec.Nombre || '';
    $('#editSecApellido').value = sec.Apellido || '';
    $('#editSecEmail').value = sec.email || '';

    showModal(modalEditSecretaria);
  }

  if (formEditSecretaria) {
    formEditSecretaria.addEventListener('submit', async (ev)=>{
      ev.preventDefault();
      const msgEl = $('#msgSecretariaModal');
      setMsg(msgEl, '');
      try{
        const fd = new FormData();
        fd.append('action', 'update_secretaria');
        fd.append('csrf_token', csrf);
        fd.append('id_secretaria', $('#editSecId').value);
        fd.append('nombre', $('#editSecNombre').value);
        fd.append('apellido', $('#editSecApellido').value);
        fd.append('email', $('#editSecEmail').value);

        const res = await fetch('admin.php', { method:'POST', body:fd, headers:{ 'Accept':'application/json' }});
        const data = await res.json();
        if (!data.ok) throw new Error(data.error || 'Error actualizando');
        
        setMsg(msgEl, data.msg || 'Secretaria actualizada', true);
        setTimeout(()=>{ hideModal(modalEditSecretaria); loadInit(); }, 1000);
      }catch(err){
        setMsg(msgEl, err.message, false);
      }
    });
  }

  // Eliminar secretaria
  async function deleteSecretaria(id){
    if (!confirm('¿Eliminar esta secretaria? Esta acción no se puede deshacer.')) return;
    try{
      const fd = new FormData();
      fd.append('action', 'delete_secretaria');
      fd.append('id_secretaria', id);
      fd.append('csrf_token', csrf);

      const res = await fetch('admin.php', { method:'POST', body:fd, headers:{ 'Accept':'application/json' }});
      const data = await res.json();
      if (!data.ok) throw new Error(data.error || 'Error eliminando');
      
      setMsg(msgCreateSec, data.msg || 'Secretaria eliminada', true);
      await loadInit();
    }catch(err){
      setMsg(msgCreateSec, err.message, false);
    }
  }

  // ========== TURNOS ==========
  
  // Cargar médicos por especialidad
  async function loadDocs(espId){
    if(!fMed) return;
    fMed.innerHTML = `<option value="">Cargando…</option>`; 
    fMed.disabled=true;
    btnNewTurno.disabled=true;
    try {
      const r = await fetch(`admin.php?fetch=doctors&especialidad_id=${encodeURIComponent(espId)}`, { headers:{ 'Accept':'application/json' }});
      const data = await r.json();
      if(!data.ok) { setMsg(msgTurns, data.error||'Error cargando médicos', false); return; }
      fMed.innerHTML = `<option value="">Elegí médico…</option>`;
      (data.items||[]).forEach(m=>{
        const opt = document.createElement('option'); 
        opt.value=m.Id_medico; 
        opt.textContent=`${m.Apellido}, ${m.Nombre}`; 
        fMed.appendChild(opt);
      });
      fMed.disabled = false;
    } catch (e) { 
      setMsg(msgTurns, 'Error cargando médicos', false); 
      console.error(e); 
    }
  }

  // Cargar agenda de turnos
  async function loadAgenda(){
    setMsg(msgTurns, '');
    tblAgendaBody.innerHTML=''; 
    noData.style.display='none';
    
    if(!fMed.value){ 
      noData.style.display='block'; 
      noData.textContent='Seleccioná una especialidad y un médico.'; 
      return; 
    }

    currentMedicoId = fMed.value;
    const qs = new URLSearchParams({ 
      fetch:'agenda', 
      medico_id:fMed.value, 
      from:(fFrom.value||''), 
      to:(fTo.value||'') 
    });

    try {
      const r = await fetch(`admin.php?${qs.toString()}`, { headers:{ 'Accept':'application/json' }});
      const data = await r.json();
      if(!data.ok){ 
        setMsg(msgTurns, data.error||'Error cargando agenda', false); 
        return; 
      }
      renderAgenda(data.items||[]);
    } catch (e) { 
      setMsg(msgTurns, 'Error cargando agenda', false); 
      console.error(e); 
    }
  }

  function renderAgenda(rows){
    tblAgendaBody.innerHTML='';
    if (!rows.length){ 
      noData.style.display = 'block'; 
      noData.textContent = 'No se encontraron turnos.'; 
      return; 
    }
    noData.style.display = 'none';
    
    rows.forEach(r=>{
      const reservado = (r.estado==='reservado');
      const tr = document.createElement('tr');
      tr.innerHTML = `
        <td>${esc(r.fecha_fmt||'')}</td>
        <td>${esc(r.paciente||'')}</td>
        <td><span class="badge ${reservado?'ok':'warn'}">${esc(r.estado||'')}</span></td>
        <td class="row-actions">
          ${reservado ? `
            <button class="btn ghost btn-cancel" data-id="${r.Id_turno}">❌ Cancelar</button>
            <button class="btn ghost btn-reprog" data-id="${r.Id_turno}" data-med="${r.Id_medico||''}">🔄 Reprogramar</button>
            <button class="btn danger btn-delete" data-id="${r.Id_turno}">🗑️ Eliminar</button>
          ` : `
            <button class="btn danger btn-delete" data-id="${r.Id_turno}">🗑️ Eliminar</button>
          `}
        </td>`;
      if(!reservado) tr.classList.add('is-cancelado');
      tblAgendaBody.appendChild(tr);
    });

    // Eventos
    $('.btn-cancel').forEach(b=>b.addEventListener('click', ()=> cancelTurno(b.dataset.id)));
    $('.btn-delete').forEach(b=>b.addEventListener('click', ()=> deleteTurno(b.dataset.id)));
    $('.btn-reprog').forEach(b=>{
      b.addEventListener('click', ()=> {
        selectedTurnoId = b.dataset.id;
        reprogSection.style.display = 'block';
        newDate.disabled=false; 
        newTime.disabled=true; 
        newTime.innerHTML=`<option value="">Elegí fecha…</option>`;
        btnReprog.disabled=true; 
        setMsg(msgTurns, '🔄 Seleccioná nueva fecha y horario para reprogramar');
        reprogSection.scrollIntoView({behavior:'smooth', block:'center'});
      });
    });
  }

  // Cancelar turno
  async function cancelTurno(id){
    if (!confirm('¿Cancelar este turno?')) return;
    try {
      const fd = new FormData(); 
      fd.append('action','cancel_turno'); 
      fd.append('turno_id', id); 
      fd.append('csrf_token', csrf);
      
      const r = await fetch('admin.php', { method:'POST', body:fd, headers:{ 'Accept':'application/json' }});
      const data = await r.json(); 
      if(!data.ok) throw new Error(data.error||'No se pudo cancelar');
      
      setMsg(msgTurns, '✅ Turno cancelado', true); 
      await loadAgenda();
    } catch (e) { 
      setMsg(msgTurns, e.message, false); 
    }
  }

  // Eliminar turno
  async function deleteTurno(id){
    if (!confirm('¿ELIMINAR este turno permanentemente? Esta acción no se puede deshacer.')) return;
    try {
      const fd = new FormData(); 
      fd.append('action','delete_turno'); 
      fd.append('turno_id', id); 
      fd.append('csrf_token', csrf);
      
      const r = await fetch('admin.php', { method:'POST', body:fd, headers:{ 'Accept':'application/json' }});
      const data = await r.json(); 
      if(!data.ok) throw new Error(data.error||'No se pudo eliminar');
      
      setMsg(msgTurns, '✅ Turno eliminado', true); 
      await loadAgenda();
    } catch (e) { 
      setMsg(msgTurns, e.message, false); 
    }
  }

  // Reprogramar: cargar slots de nueva fecha
  newDate?.addEventListener('change', async ()=>{
    setMsg(msgTurns, ''); 
    newTime.innerHTML=`<option value="">Cargando…</option>`; 
    newTime.disabled=true; 
    btnReprog.disabled=true;
    
    if(!newDate.value || !currentMedicoId){ 
      newTime.innerHTML=`<option value="">Elegí fecha…</option>`; 
      return; 
    }
    
    const qs = new URLSearchParams({ 
      fetch:'slots', 
      date:newDate.value, 
      medico_id:currentMedicoId 
    });
    
    try {
      const r = await fetch(`admin.php?${qs.toString()}`, { headers:{ 'Accept':'application/json' }});
      const data = await r.json();
      if(!data.ok) { 
        setMsg(msgTurns, data.error||'Error cargando horarios', false); 
        newTime.innerHTML=`<option value="">Error</option>`; 
        return; 
      }
      newTime.innerHTML = `<option value="">Elegí horario…</option>`;
      (data.slots||[]).forEach(h => { 
        const opt=document.createElement('option'); 
        opt.value=h; 
        opt.textContent=h; 
        newTime.appendChild(opt); 
      });
      newTime.disabled = false;
    } catch (e) { 
      setMsg(msgTurns, 'Error cargando horarios', false); 
      console.error(e); 
    }
  });

  newTime?.addEventListener('change', ()=> { 
    btnReprog.disabled = !(selectedTurnoId && newDate.value && newTime.value); 
  });

  // Confirmar reprogramación
  btnReprog?.addEventListener('click', async ()=>{
    if(!selectedTurnoId || !currentMedicoId || !newDate.value || !newTime.value){ 
      setMsg(msgTurns, 'Completá turno, fecha y hora', false); 
      return; 
    }
    
    try {
      const fd = new FormData();
      fd.append('action','reschedule_turno'); 
      fd.append('turno_id', selectedTurnoId); 
      fd.append('medico_id', currentMedicoId);
      fd.append('date', newDate.value); 
      fd.append('time', newTime.value); 
      fd.append('csrf_token', csrf);
      
      const r = await fetch('admin.php', { method:'POST', body:fd, headers:{ 'Accept':'application/json' }});
      const data = await r.json(); 
      if(!data.ok) throw new Error(data.error||'No se pudo reprogramar');
      
      setMsg(msgTurns, '✅ Turno reprogramado', true); 
      selectedTurnoId=null; 
      btnReprog.disabled=true; 
      reprogSection.style.display='none';
      await loadAgenda(); 
      newTime.innerHTML=`<option value="">Elegí fecha…</option>`; 
      newTime.disabled=true; 
      newDate.value='';
    } catch (e) { 
      setMsg(msgTurns, e.message, false); 
    }
  });

  btnCancelReprog?.addEventListener('click', ()=>{
    selectedTurnoId = null;
    reprogSection.style.display = 'none';
    newDate.value = '';
    newTime.innerHTML = `<option value="">Elegí fecha…</option>`;
    setMsg(msgTurns, '');
  });

  // ========== CREAR TURNO ==========
  
  btnNewTurno?.addEventListener('click', ()=>{
    if (!currentMedicoId) {
      setMsg(msgTurns, 'Seleccioná un médico primero', false);
      return;
    }
    selectedPacienteId.value = '';
    selectedPacienteInfo.textContent = 'Ninguno';
    selectedPacienteInfo.style.color = 'var(--muted)';
    turnoDate.value = '';
    turnoTime.innerHTML = `<option value="">Elegí fecha primero...</option>`;
    searchPaciente.value = '';
    pacienteResults.innerHTML = '';
    setMsg(msgModal, '');
    showModal(modalCreateTurno);
  });

  // Buscar paciente
  let searchTimeout;
  searchPaciente?.addEventListener('input', ()=>{
    clearTimeout(searchTimeout);
    const query = searchPaciente.value.trim();
    
    if (query.length < 2) {
      pacienteResults.innerHTML = '';
      return;
    }

    searchTimeout = setTimeout(async ()=>{
      try {
        const res = await fetch(`admin.php?fetch=search_pacientes&q=${encodeURIComponent(query)}`, {
          headers:{ 'Accept':'application/json' }
        });
        const data = await res.json();
        if (!data.ok) throw new Error(data.error || 'Error buscando');
        
        renderPacienteResults(data.items || []);
      } catch (e) {
        pacienteResults.innerHTML = `<div style="padding:10px;color:var(--err)">${e.message}</div>`;
      }
    }, 300);
  });

  function renderPacienteResults(items){
    pacienteResults.innerHTML = '';
    if (!items.length) {
      pacienteResults.innerHTML = '<div style="padding:10px;color:var(--muted)">No se encontraron pacientes</div>';
      return;
    }

    items.forEach(p=>{
      const div = document.createElement('div');
      div.className = 'paciente-item';
      div.innerHTML = `
        <strong>${esc(p.Apellido)}, ${esc(p.Nombre)}</strong><br>
        <small style="color:var(--muted)">DNI: ${esc(p.dni)} | ${esc(p.email)}</small>
      `;
      div.addEventListener('click', ()=>{
        selectedPacienteId.value = p.Id_paciente;
        selectedPacienteInfo.innerHTML = `<strong>${esc(p.Apellido)}, ${esc(p.Nombre)}</strong><br><small>DNI: ${esc(p.dni)} | ${esc(p.Obra_social || 'Sin obra social')}</small>`;
        selectedPacienteInfo.style.color = 'var(--ok)';
        $('.paciente-item').forEach(item => item.classList.remove('selected'));
        div.classList.add('selected');
      });
      pacienteResults.appendChild(div);
    });
  }

  // Cargar slots para turno nuevo
  turnoDate?.addEventListener('change', async ()=>{
    turnoTime.innerHTML = `<option value="">Cargando…</option>`;
    turnoTime.disabled = true;
    
    if (!turnoDate.value || !currentMedicoId) {
      turnoTime.innerHTML = `<option value="">Elegí fecha…</option>`;
      return;
    }

    try {
      const res = await fetch(`admin.php?fetch=slots&date=${turnoDate.value}&medico_id=${currentMedicoId}`, {
        headers:{ 'Accept':'application/json' }
      });
      const data = await res.json();
      if (!data.ok) throw new Error(data.error || 'Error cargando horarios');
      
      turnoTime.innerHTML = `<option value="">Elegí horario…</option>`;
      (data.slots || []).forEach(slot=>{
        const opt = document.createElement('option');
        opt.value = slot;
        opt.textContent = slot;
        turnoTime.appendChild(opt);
      });
      turnoTime.disabled = false;
    } catch (e) {
      setMsg(msgModal, e.message, false);
      turnoTime.innerHTML = `<option value="">Error</option>`;
    }
  });

  // Crear turno
  formCreateTurno?.addEventListener('submit', async (ev)=>{
    ev.preventDefault();
    setMsg(msgModal, '');

    const pacId = selectedPacienteId.value;
    const date = turnoDate.value;
    const time = turnoTime.value;

    if (!pacId) {
      setMsg(msgModal, 'Seleccioná un paciente', false);
      return;
    }
    if (!date || !time) {
      setMsg(msgModal, 'Completá fecha y horario', false);
      return;
    }

    try {
      const fd = new FormData();
      fd.append('action', 'create_turno');
      fd.append('medico_id', currentMedicoId);
      fd.append('paciente_id', pacId);
      fd.append('date', date);
      fd.append('time', time);
      fd.append('csrf_token', csrf);

      const res = await fetch('admin.php', { method:'POST', body:fd, headers:{ 'Accept':'application/json' }});
      const data = await res.json();
      if (!data.ok) throw new Error(data.error || 'Error creando turno');

      setMsg(msgModal, '✅ ' + (data.msg || 'Turno creado'), true);
      setTimeout(()=>{
        hideModal(modalCreateTurno);
        loadAgenda();
      }, 1000);
    } catch (e) {
      setMsg(msgModal, e.message, false);
    }
  });

  // Cerrar modales
  btnCloseModal?.addEventListener('click', ()=> hideModal(modalCreateTurno));
  btnCloseMedicoModal?.addEventListener('click', ()=> hideModal(modalEditMedico));
  btnCloseSecretariaModal?.addEventListener('click', ()=> hideModal(modalEditSecretaria));

  // Cerrar modal al hacer click fuera
  [modalCreateTurno, modalEditMedico, modalEditSecretaria].forEach(modal=>{
    modal?.addEventListener('click', (e)=>{
      if (e.target === modal) hideModal(modal);
    });
  });

  // ========== EVENTOS FILTROS ==========
  
  btnRefresh?.addEventListener('click', loadAgenda);
  btnClearDates?.addEventListener('click', ()=>{ 
    fFrom.value=''; 
    fTo.value=''; 
    loadAgenda(); 
  });

  fEsp?.addEventListener('change', async ()=>{
    setMsg(msgTurns, ''); 
    tblAgendaBody.innerHTML=''; 
    noData.style.display='block';
    noData.textContent='Seleccioná un médico para ver sus turnos.';
    selectedTurnoId=null; 
    btnReprog.disabled=true; 
    reprogSection.style.display='none';
    newDate.value=''; 
    newTime.innerHTML=`<option value="">Elegí fecha…</option>`; 
    newTime.disabled=true;
    currentMedicoId = null;
    btnNewTurno.disabled = true;
    
    if(!fEsp.value){ 
      fMed.innerHTML=`<option value="">Elegí especialidad…</option>`; 
      fMed.disabled=true; 
      noData.textContent='Seleccioná una especialidad primero.';
      return; 
    }
    await loadDocs(fEsp.value);
  });

  fMed?.addEventListener('change', async ()=>{ 
    setMsg(msgTurns, ''); 
    reprogSection.style.display='none';
    newDate.value=''; 
    newTime.innerHTML=`<option value="">Elegí fecha…</option>`; 
    newTime.disabled=true;
    currentMedicoId = fMed.value || null;
    btnNewTurno.disabled = !currentMedicoId;
    await loadAgenda(); 
  });

  fFrom?.addEventListener('change', loadAgenda); 
  fTo?.addEventListener('change', loadAgenda);

  // ========== INICIAL ==========
  (async function init(){
    await loadInit();
  })();

})();