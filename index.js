(function(){
  const $ = s => document.querySelector(s);
  const msg = $('#msg');
  const slotsBox = $('#slots');
  const btnReservar = $('#btnReservar');
  const csrf = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';
  const calTitle = $('#calTitle');
  const calGrid  = $('#calGrid');
  const calPrev  = $('#calPrev');
  const calNext  = $('#calNext');
  const selEsp   = $('#selEsp');
  const selMedico= $('#selMedico');
  const tblBody  = $('#tblTurnos tbody');

  const MONTHS = ['enero','febrero','marzo','abril','mayo','junio','julio','agosto','septiembre','octubre','noviembre','diciembre'];

  // Estado UI
  let current = new Date(); current.setHours(0,0,0,0); current.setDate(1);
  let selectedDate = null; // 'YYYY-MM-DD'
  let selectedSlot = null; // 'HH:MM'
  let selectedApptId = null; // si está seteado, el botón reservar actúa como reprogramación

  function setMsg(t, ok=true){
    if(!msg) return;
    msg.textContent = t || '';
    msg.classList.remove('ok','err');
    msg.classList.add(ok?'ok':'err');
  }

  function toYMD(d){ const y=d.getFullYear(); const m=String(d.getMonth()+1).padStart(2,'0'); const dd=String(d.getDate()).padStart(2,'0'); return `${y}-${m}-${dd}`; }
  function isWeekday(d){ const w=d.getDay(); return w>=1 && w<=5; }
  function isPast(d){ const t=new Date(); t.setHours(0,0,0,0); return d<t; }

  async function loadEspecialidades(){
    selEsp.innerHTML = `<option value="">Cargando…</option>`;
    selMedico.innerHTML = `<option value="">Elegí especialidad…</option>`;
    selMedico.disabled = true;
    const res = await fetch('turnos_api.php?action=specialties',{headers:{'Accept':'application/json'}});
    const data = await res.json();
    if(!data.ok){ setMsg(data.error||'Error cargando especialidades', false); return; }
    selEsp.innerHTML = `<option value="">Elegí especialidad…</option>`;
    (data.items||[]).forEach(e=>{
      const opt=document.createElement('option');
      opt.value=e.Id_Especialidad; opt.textContent=e.Nombre;
      selEsp.appendChild(opt);
    });
  }

  async function loadMedicosByEsp(espId){
    selMedico.innerHTML = `<option value="">Cargando…</option>`;
    selMedico.disabled = true;
    const res = await fetch(`turnos_api.php?action=doctors&especialidad_id=${encodeURIComponent(espId)}`,{headers:{'Accept':'application/json'}});
    const data = await res.json();
    if(!data.ok){ setMsg(data.error||'Error cargando médicos', false); return; }
    selMedico.innerHTML = `<option value="">Elegí médico…</option>`;
    (data.items||[]).forEach(m=>{
      const opt=document.createElement('option');
      opt.value=m.Id_medico; opt.textContent=`${m.Apellido}, ${m.Nombre}`;
      selMedico.appendChild(opt);
    });
    selMedico.disabled = false;
  }

  async function loadMyAppointments(){
    const res = await fetch('turnos_api.php?action=my_appointments',{headers:{'Accept':'application/json'}});
    const data = await res.json();
    if(!data.ok){ setMsg(data.error||'Error cargando mis turnos', false); return; }
    renderAppointments(data.items||[]);
  }

  function renderAppointments(rows){
    tblBody.innerHTML='';
    rows.forEach(r=>{
      const tr=document.createElement('tr');
      const acciones = (r.estado === 'reservado')
        ? `<button class="btn ghost btn-cancel" data-id="${r.Id_turno}">Cancelar</button>
           <button class="btn ghost btn-reprog" data-id="${r.Id_turno}" data-med="${r.Id_medico||''}">Elegir nuevo horario</button>`
        : ''; // 🔒 si está cancelado u otro estado, no hay acciones
      tr.innerHTML = `
        <td>${escapeHtml(r.fecha_fmt||'')}</td>
        <td>${escapeHtml(r.medico||'')}</td>
        <td>${escapeHtml(r.especialidad||'')}</td>
        <td><span class="badge ${r.estado==='reservado'?'ok':'warn'}">${escapeHtml(r.estado||'')}</span></td>
        <td class="row-actions">${acciones}</td>`;
      tblBody.appendChild(tr);
    });

    // Bind acciones (solo filas con estado reservado)
    tblBody.querySelectorAll('.btn-cancel').forEach(b=>{
      b.addEventListener('click', ()=> onCancel(b.dataset.id));
    });
    tblBody.querySelectorAll('.btn-reprog').forEach(b=>{
      b.addEventListener('click', ()=> {
        selectedApptId = b.dataset.id;
        const med = b.dataset.med || '';
        if (med) {
          // seleccionar automáticamente el médico del turno
          if (selMedico && selMedico.value !== String(med)) {
            selMedico.value = String(med);
          }
        }
        btnReservar.textContent = 'Confirmar reprogramación';
        setMsg('Seleccioná un día y horario nuevo, luego confirma la reprogramación.');
        // refrescar slots del día seleccionado si ya había uno
        if (selectedDate && selMedico.value) fetchSlots(selectedDate, selMedico.value);
      });
    });
  }

  function renderCalendar(){
    calTitle.textContent = `${MONTHS[current.getMonth()]} ${current.getFullYear()}`;
    selectedDate = null;
    selectedSlot = null;
    btnReservar.disabled = true;
    setMsg('');
    slotsBox.textContent = 'Elegí un día disponible…';

    calGrid.innerHTML = '';
    const year = current.getFullYear();
    const month = current.getMonth();
    const first = new Date(year, month, 1);
    const last  = new Date(year, month+1, 0);
    let offset = (first.getDay()+6)%7; // lunes=0
    for(let i=0;i<offset;i++){ const b=document.createElement('div'); b.className='day muted'; calGrid.appendChild(b); }
    for(let d=1; d<=last.getDate(); d++){
      const cell = document.createElement('div');
      cell.className='day';
      cell.textContent=d;
      const dateObj = new Date(year, month, d); dateObj.setHours(0,0,0,0);
      const available = isWeekday(dateObj) && !isPast(dateObj);
      if(available){ cell.classList.add('available'); cell.addEventListener('click', ()=> selectDay(dateObj, cell)); }
      calGrid.appendChild(cell);
    }
  }

  function highlightSelection(cell){ document.querySelectorAll('.day.selected').forEach(el=>el.classList.remove('selected')); cell?.classList.add('selected'); }

  async function selectDay(dateObj, cell){
    selectedDate = toYMD(dateObj);
    selectedSlot = null;
    btnReservar.disabled = true;
    highlightSelection(cell);
    if(!selMedico.value){ slotsBox.textContent='Elegí especialidad y médico…'; return; }
    await fetchSlots(selectedDate, selMedico.value);
  }

  function renderSlots(list){
    slotsBox.innerHTML = '';
    if(!Array.isArray(list) || list.length===0){
      slotsBox.textContent = 'No hay horarios disponibles';
      btnReservar.disabled = true;
      selectedSlot = null;
      return;
    }
    list.forEach(hhmm=>{
      const b = document.createElement('button');
      b.type='button';
      b.className='slot';
      b.textContent=hhmm;
      b.addEventListener('click', ()=>{
        selectedSlot = hhmm;
        document.querySelectorAll('.slot').forEach(x=>x.classList.remove('sel'));
        b.classList.add('sel');
        btnReservar.disabled = !selMedico.value;
        setMsg('');
      });
      slotsBox.appendChild(b);
    });
  }

  async function fetchSlots(dateStr, medicoId){
    slotsBox.textContent='Cargando…';
    btnReservar.disabled = true;
    selectedSlot = null;
    try{
      const res = await fetch(`turnos_api.php?action=slots&date=${encodeURIComponent(dateStr)}&medico_id=${encodeURIComponent(medicoId)}`,{headers:{'Accept':'application/json'}});
      const data = await res.json();
      if(!data.ok) throw new Error(data.error||'Error al cargar');
      renderSlots(data.slots||[]);
    }catch(e){
      setMsg(e.message,false);
      slotsBox.textContent='Error al cargar horarios';
    }
  }

  async function onCancel(turnoId){
    try{
      const fd = new FormData();
      fd.append('action','cancel');
      fd.append('turno_id', turnoId);
      fd.append('csrf_token', csrf);
      const res = await fetch('turnos_api.php',{method:'POST', body:fd, headers:{'Accept':'application/json','X-CSRF-Token':csrf}});
      const data = await res.json();
      if(!data.ok) throw new Error(data.error||'No se pudo cancelar');
      setMsg('Turno cancelado', true);
      selectedApptId = null;
      btnReservar.textContent = 'Reservar';
      await loadMyAppointments();
      if (selectedDate && selMedico.value) await fetchSlots(selectedDate, selMedico.value);
    }catch(e){
      setMsg(e.message,false);
    }
  }

  btnReservar?.addEventListener('click', async ()=>{
    setMsg('');
    if(!selMedico.value){ setMsg('Elegí un médico', false); return; }
    if(!selectedDate || !selectedSlot){ setMsg('Elegí un día y un horario', false); return; }

    // Modo reprogramación si hay un turno seleccionado desde la lista
    const isReschedule = !!selectedApptId;

    try{
      const fd=new FormData();
      fd.append('date', selectedDate);
      fd.append('time', selectedSlot);
      fd.append('medico_id', selMedico.value);
      fd.append('csrf_token', csrf);

      if (isReschedule) {
        fd.append('action','reschedule');
        fd.append('turno_id', selectedApptId);
      } else {
        fd.append('action','book');
      }

      const res = await fetch('turnos_api.php',{method:'POST', body:fd, headers:{'Accept':'application/json','X-CSRF-Token':csrf}});
      const data = await res.json();
      if(!data.ok) throw new Error(data.error|| (isReschedule ? 'No se pudo reprogramar' : 'No se pudo reservar'));

      setMsg(isReschedule ? '✅ Turno reprogramado' : '✅ Turno reservado', true);
      await loadMyAppointments();
      await fetchSlots(selectedDate, selMedico.value);

      btnReservar.disabled = true; selectedSlot=null;
      if (isReschedule) {
        selectedApptId = null;
        btnReservar.textContent = 'Reservar';
      }
    }catch(e){ setMsg(e.message,false); }
  });

  // Selectores
  selEsp?.addEventListener('change', async ()=>{
    setMsg('');
    selectedDate=null; selectedSlot=null; btnReservar.disabled=true;
    slotsBox.textContent='Elegí un día disponible…';
    if(!selEsp.value){ selMedico.innerHTML=`<option value="">Elegí especialidad…</option>`; selMedico.disabled=true; return; }
    await loadMedicosByEsp(selEsp.value);
  });
  selMedico?.addEventListener('change', ()=>{
    setMsg('');
    selectedSlot=null; btnReservar.disabled=true;
    slotsBox.textContent = selectedDate ? 'Elegí un horario…' : 'Elegí un día disponible…';
    if(selectedDate && selMedico.value) fetchSlots(selectedDate, selMedico.value);
  });

  // Calendario navegación
  calPrev?.addEventListener('click', ()=>{ current.setMonth(current.getMonth()-1); renderCalendar(); });
  calNext?.addEventListener('click', ()=>{ current.setMonth(current.getMonth()+1); renderCalendar(); });

  function renderCalendar(){
    calTitle.textContent = `${MONTHS[current.getMonth()]} ${current.getFullYear()}`;
    selectedDate = null; selectedSlot=null; btnReservar.disabled=true;
    slotsBox.textContent='Elegí un día disponible…';
    calGrid.innerHTML='';

    const year = current.getFullYear(), month=current.getMonth();
    const first=new Date(year,month,1), last=new Date(year,month+1,0);
    let offset=(first.getDay()+6)%7; // lunes=0
    for(let i=0;i<offset;i++){ const b=document.createElement('div'); b.className='day muted'; calGrid.appendChild(b); }
    for(let d=1; d<=last.getDate(); d++){
      const cell=document.createElement('div'); cell.className='day'; cell.textContent=d;
      const dateObj=new Date(year,month,d); dateObj.setHours(0,0,0,0);
      const available = isWeekday(dateObj) && !isPast(dateObj);
      if(available){ cell.classList.add('available'); cell.addEventListener('click', ()=> selectDay(dateObj, cell)); }
      calGrid.appendChild(cell);
    }
  }
  function selectDay(dateObj, cell){
    document.querySelectorAll('.day.selected').forEach(el=>el.classList.remove('selected'));
    cell?.classList.add('selected');
    selectedDate = toYMD(dateObj); selectedSlot=null; btnReservar.disabled=true;
    if(!selMedico.value){ slotsBox.textContent='Elegí especialidad y médico…'; return; }
    fetchSlots(selectedDate, selMedico.value);
  }

  function escapeHtml(s){ return String(s??'').replace(/[&<>"']/g, c=>({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c])); }

  // Inicial
  (async function init(){
    await loadEspecialidades();
    await loadMyAppointments();
    renderCalendar();
    // Estado inicial del botón
    btnReservar.textContent = 'Reservar';
  })();
})();
