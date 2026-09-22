(() => {
  'use strict';
  const data = window.skillBridge || {candidates:[], opportunities:[], applications:[], trend:[], skills:[]};
  const csrfToken = data.csrfToken || '';
  function postForm(fields) {
    const f = document.createElement('form');
    f.method = 'post';
    Object.entries({...fields, csrf_token: csrfToken}).forEach(([k, v]) => {
      const i = document.createElement('input');
      i.type = 'hidden';
      i.name = k;
      i.value = String(v);
      f.appendChild(i);
    });
    document.body.appendChild(f);
    f.submit();
  }
  const $ = (s, r=document) => r.querySelector(s);
  const $$ = (s, r=document) => [...r.querySelectorAll(s)];
  const esc = (v='') => String(v).replace(/[&<>'"]/g, c => ({'&':'&amp;','<':'&lt;','>':'&gt;',"'":'&#039;','"':'&quot;'}[c]));
  const skillsOf = v => String(v || '').split(/[,\n]+/).map(x=>x.trim().toLowerCase()).filter(Boolean);
  const icon = (name) => {
    const map = {arrow:'M5 12h14M13 6l6 6-6 6',eye:'M2 12s3.5-6 10-6 10 6 10 6-3.5 6-10 6S2 12 2 12zM12 9.5a2.5 2.5 0 1 0 0 5 2.5 2.5 0 0 0 0-5'};
    return `<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="${map[name]||map.arrow}"/></svg>`;
  };

  function activateSection(id, updateHash=true) {
    const section = document.getElementById(id) || document.getElementById('dashboard');
    $$('.page-section').forEach(s=>s.classList.toggle('active', s===section));
    $$('.nav-item').forEach(n=>n.classList.toggle('active', n.dataset.section===section.id));
    if(updateHash) history.replaceState(null,'','#'+section.id);
    $('.sidebar')?.classList.remove('open');
    if(section.id==='analytics') drawChart('analyticsChart');
    if(section.id==='dashboard') drawChart('applicationChart');
  }

  $$('[data-section]').forEach(a=>a.addEventListener('click', e=>{e.preventDefault();activateSection(a.dataset.section);}));
  $$('[data-go]').forEach(b=>b.addEventListener('click',()=>activateSection(b.dataset.go)));
  $('.mobile-menu')?.addEventListener('click',()=>$('.sidebar')?.classList.toggle('open'));
  window.addEventListener('hashchange',()=>activateSection(location.hash.slice(1)||'dashboard',false));
  activateSection(location.hash.slice(1)||'dashboard',false);

  function openModal(id){ document.getElementById(id)?.classList.add('show'); document.body.classList.add('modal-open'); }
  function closeModals(){ $$('.modal').forEach(m=>m.classList.remove('show')); document.body.classList.remove('modal-open'); }
  $$('[data-close]').forEach(b=>b.addEventListener('click',closeModals));
  $$('.modal').forEach(m=>m.addEventListener('click',e=>{if(e.target===m)closeModals();}));
  document.addEventListener('keydown',e=>{if(e.key==='Escape')closeModals();});

  function setOpportunityMode(type){
    const isIntern = type==='internship' || type==='Internship';
    $('#oppType').value = isIntern ? 'Internship':'Full-time Job';
    $('#opportunityModalTitle').textContent = isIntern ? 'Post New Internship':'Post New Job';
    $$('.intern-only').forEach(x=>x.style.display=isIntern?'flex':'none');
    $$('.job-only').forEach(x=>x.style.display=isIntern?'none':'flex');
    $('#oppExperience')?.toggleAttribute('required', !isIntern);
    $('#oppSalary')?.toggleAttribute('required', !isIntern);
    $('#oppStipend')?.toggleAttribute('required', isIntern);
    $('#oppDuration')?.toggleAttribute('required', isIntern);
    $('#oppEligibility')?.toggleAttribute('required', isIntern);
  }
  function setOpportunityDeadlineMin(){
    const d=new Date();
    const iso=new Date(d.getTime()-d.getTimezoneOffset()*60000).toISOString().slice(0,10);
    $('#oppDeadline')?.setAttribute('min', iso);
  }
  $$('[data-open-opportunity]').forEach(b=>b.addEventListener('click',()=>{
    const form=$('#opportunityForm');
    if(form?.tagName==='FORM') form.reset();
    $('#oppId').value='';
    $('#oppStatus').value='Draft';
    $('#oppAssessment').value='';
    setOpportunityDeadlineMin();
    setOpportunityMode(b.dataset.openOpportunity);
    openModal('opportunityModal');
  }));
  $('#oppType')?.addEventListener('change',()=>setOpportunityMode($('#oppType').value));

  const byId = id => data.opportunities.find(o=>Number(o.id)===Number(id));
  $$('[data-edit-opportunity]').forEach(b=>b.addEventListener('click',()=>{
    const o=byId(b.dataset.editOpportunity); if(!o)return;
    $('#oppId').value=o.id;
    $('#oppType').value=o.type||'Full-time Job';
    $('#oppTitle').value=o.title||'';
    $('#oppDepartment').value=o.department||'';
    $('#oppLocation').value=o.location||'';
    $('#oppWorkMode').value=o.work_mode||'In-office';
    $('#oppSkills').value=o.mapped_skills||o.required_skills||'';
    $('#oppOpenings').value=o.openings||1;
    $('#oppExperience').value=o.experience||'';
    $('#oppQualification').value=o.qualification||'';
    $('#oppCgpa').value=o.min_cgpa??'';
    $('#oppBranches').value=o.allowed_branches||'';
    $('#oppSalary').value=o.salary||'';
    $('#oppStipend').value=o.stipend||'';
    $('#oppDuration').value=o.duration||'';
    $('#oppEligibility').value=o.eligibility||'';
    $('#oppDeadline').value=o.deadline||'';
    $('#oppStatus').value=o.status||'Draft';
    $('#oppDescription').value=o.description||'';
    $('#oppLearningOutcomes').value=o.learning_outcomes||'';
    $('#oppAssessment').value=o.assessment_id||'';
    setOpportunityDeadlineMin();
    setOpportunityMode(o.type);
    $('#opportunityModalTitle').textContent='Edit Opportunity';
    openModal('opportunityModal');
  }));
  $$('[data-view-opportunity]').forEach(b=>b.addEventListener('click',()=>{
    const o=byId(b.dataset.viewOpportunity); if(!o)return;
    $('#viewContent').innerHTML=`<span class="eyebrow">OPPORTUNITY DETAILS</span><h2>${esc(o.title)}</h2><p class="modal-subtitle">${esc(o.type)} • ${esc(o.location||'Location not specified')}</p><div class="detail-grid"><div><small>Department</small><strong>${esc(o.department||'—')}</strong></div><div><small>Status</small><strong>${esc(o.status)}</strong></div><div><small>Required skills</small><strong>${esc(o.mapped_skills||o.required_skills||'—')}</strong></div><div><small>Qualification</small><strong>${esc(o.qualification||'—')}</strong></div><div><small>Applications</small><strong>${Number(o.application_count||0)}</strong></div><div><small>Deadline</small><strong>${esc(o.deadline||'—')}</strong></div><div><small>Salary / Stipend</small><strong>${esc(o.type==='Internship'?(o.stipend||'—'):(o.salary||'—'))}</strong></div><div><small>Duration / Experience</small><strong>${esc(o.type==='Internship'?(o.duration||'—'):(o.experience||'—'))}</strong></div></div><div class="detail-description"><small>Description</small><p>${esc(o.description||'No description provided.')}</p></div>`; openModal('viewModal');
  }));
  $$('[data-delete-opportunity]').forEach(b=>b.addEventListener('click',()=>{
    if(!confirm('Delete this opportunity? If applications exist, it will be safely closed instead.'))return;
    postForm({action:'delete_opportunity', id:b.dataset.deleteOpportunity});
  }));

  function filterRows(tableId, searchId, statusId, departmentId){
    const table=$('#'+tableId), search=$('#'+searchId), status=$('#'+statusId), dept=departmentId?$('#'+departmentId):null; if(!table)return;
    const apply=()=>{const q=(search?.value||'').toLowerCase().trim(), st=status?.value||'', dp=dept?.value||''; $$('tbody tr',table).forEach(r=>{const ok=(!q||r.dataset.search.includes(q))&&(!st||r.dataset.status===st)&&(!dp||r.dataset.department===dp);r.style.display=ok?'':'none';});};
    [search,status,dept].filter(Boolean).forEach(x=>x.addEventListener('input',apply)); apply();
  }
  filterRows('jobsTable','jobSearch','jobStatus','jobDepartment'); filterRows('internshipsTable','internshipSearch','internshipStatus'); filterRows('applicationsTable','applicationSearch','applicationStatus');

  function candidateRender(){
    const root=$('#candidateGrid'); if(!root)return;
    const q=($('#candidateSearch')?.value||'').toLowerCase().trim(), inst=$('#candidateInstitute')?.value||'', min=Number($('#candidateScore')?.value||0);
    const list=data.candidates.filter(c=>{const text=`${c.name} ${c.institute_name||c.institute} ${c.role_name||''} ${c.degree||''} ${c.branch||''} ${c.skill_text||''}`.toLowerCase();return (!q||text.includes(q))&&(!inst||(c.institute_name||c.institute)===inst);});
    root.innerHTML=list.map(c=>{let initials='';String(c.name||'').split(/\s+/).slice(0,2).forEach(n=>initials+=n[0]||'');const isStarred=Number(c.is_shortlisted)>0;return `<article class="card candidate-card"><div class="candidate-top"><span class="candidate-avatar">${esc(initials.toUpperCase())}</span><div><span class="match-badge high">Verified Skills</span>${isStarred?' <span style="background:#fef3c7; color:#b45309; padding:2px 8px; border-radius:12px; font-size:11px; font-weight:700;">⭐ Shortlisted</span>':''}</div></div><h3>${esc(c.name)}</h3><p>${esc(c.role_name||c.preferred_role||'Candidate')} • ${esc(c.institute_name||c.institute||'Institute not specified')}</p><div class="candidate-info"><span>${esc(c.degree||c.qualification||'Qualification')}${c.branch?' ('+esc(c.branch)+')':''}</span><span>CGPA: ${esc(c.cgpa||'N/A')}</span></div><div class="chips">${skillsOf(c.skill_text).slice(0,6).map(s=>`<span>${esc(s)}</span>`).join('')}</div><div style="display:flex; gap:8px; margin-top:12px;"><button class="btn secondary" style="flex:1;" data-candidate-open="${Number(c.id)}">View Profile ${icon('arrow')}</button><form method="post" style="display:inline;"><input type="hidden" name="action" value="toggle_shortlist"><input type="hidden" name="candidate_id" value="${Number(c.id)}"><input type="hidden" name="csrf_token" value="${esc(csrfToken)}"><button type="submit" class="btn ${isStarred?'secondary':'primary'}" style="padding:6px 12px; font-size:12px;">${isStarred?'Unstar':'⭐ Shortlist'}</button></form></div></article>`;}).join('') || '<div class="card empty-state">No candidates match the current filters.</div>';
    $$('[data-candidate-open]',root).forEach(b=>b.addEventListener('click',()=>openCandidate(b.dataset.candidateOpen)));
  }
  ['candidateSearch','candidateInstitute','candidateScore'].forEach(id=>$('#'+id)?.addEventListener('input',candidateRender));
  candidateRender();
  function openCandidate(id){const c=data.candidates.find(x=>Number(x.id)===Number(id));if(!c)return;$('#viewContent').innerHTML=`<span class="eyebrow">CANDIDATE TALENT PROFILE</span><h2>${esc(c.name)}</h2><p class="modal-subtitle">${esc(c.role_name||c.preferred_role||'Candidate')} • ${esc(c.institute_name||c.institute||'')}</p><div class="detail-grid"><div><small>Opportunity match</small><strong>Calculated per opportunity</strong></div><div><small>Degree & Branch</small><strong>${esc((c.degree||c.qualification||'B.Tech')+' '+(c.branch||''))}</strong></div><div><small>Semester / CGPA</small><strong>Sem ${esc(c.semester||7)} (CGPA: ${esc(c.cgpa||'N/A')})</strong></div><div><small>Graduation</small><strong>${esc(c.graduation_year||'2026')}</strong></div><div><small>Email</small><strong>${esc(c.email||'—')}</strong></div><div><small>Location</small><strong>${esc(c.location||'—')}</strong></div></div><div class="detail-description"><small>Verified Skills</small><div class="chips">${skillsOf(c.skill_text).map(s=>`<span>${esc(s)}</span>`).join('')||'<span>No skills recorded</span>'}</div><small>Bio & Career Interest</small><p>${esc(c.bio||'No bio recorded.')}</p>${c.linkedin_url?`<p><a href="${esc(c.linkedin_url)}" target="_blank" style="color:#2563eb; font-weight:600;">🔗 LinkedIn Profile</a></p>`:''}</div>`;openModal('viewModal');}

  async function matchingRender(){
    const id=Number($('#matchingOpportunity')?.value||0), root=$('#matchingContent');
    if(!root)return;
    if(!id){root.innerHTML='<div class="empty-state">Select an opportunity to calculate skill compatibility from MySQL data.</div>';return;}
    root.innerHTML='<div class="empty-state">Calculating server-side match scores...</div>';
    try {
      const res = await fetch(`api/matching_api.php?opportunity_id=${encodeURIComponent(id)}`);
      const payload = await res.json();
      if(!payload.ok){ root.innerHTML=`<div class="empty-state">${esc(payload.message||'Unable to load matches.')}</div>`; return; }
      const rows = (payload.candidates||[]).slice(0,15);
      root.innerHTML = rows.map(c=>{
        const matched = (c.matched_skills||[]).map(s=>s.name);
        const gaps = (c.missing_skills||[]).concat((c.skill_gaps||[]).map(g=>g.name)).filter((v,i,a)=>a.indexOf(v)===i);
        const bd = c.breakdown||{};
        return `<div class="match-row"><div class="candidate-avatar small">${esc(String(c.name||'').split(/\s+/).map(x=>x[0]).slice(0,2).join('').toUpperCase())}</div><div class="match-main"><strong>${esc(c.name)}</strong><small>${esc(c.institute_name||'')}</small><div class="chips tiny">${matched.map(s=>`<span>${esc(s)}</span>`).join('')||'<span>No matched skills</span>'}</div><small style="color:#64748b;">Skills ${Math.round(bd.skill_compatibility?.points||0)}/50 · Qual ${Math.round(bd.qualification?.points||0)}/15 · CGPA ${Math.round(bd.cgpa?.points||0)}/10 · Verified ${Math.round(bd.assessment?.points||0)}/15 · Branch ${Math.round(bd.branch?.points||0)}/10</small></div><div class="match-score"><strong>${Math.round(Number(c.total_score||0))}%</strong><small>${gaps.length} gap${gaps.length===1?'':'s'} · ${c.eligible?'Eligible':'Review'}</small></div></div>`;
      }).join('') || '<div class="empty-state">No candidates available.</div>';
    } catch (err) {
      root.innerHTML='<div class="empty-state">Unable to load match results.</div>';
    }
  }
  $('#matchingOpportunity')?.addEventListener('change',matchingRender);

  async function gapRender(){
    const oid=Number($('#gapOpportunity')?.value||0), cid=Number($('#gapCandidate')?.value||0), root=$('#gapContent');
    if(!root)return;
    if(!oid||!cid){root.innerHTML='<div class="card empty-state">Choose a candidate and opportunity.</div>';return;}
    const o=byId(oid), c=data.candidates.find(x=>Number(x.id)===cid);
    if(!o||!c){root.innerHTML='<div class="card empty-state">Invalid selection.</div>';return;}
    root.innerHTML='<div class="card empty-state">Loading explainable match...</div>';
    try {
      const res = await fetch(`api/matching_api.php?opportunity_id=${encodeURIComponent(oid)}&candidate_id=${encodeURIComponent(cid)}`);
      const payload = await res.json();
      if(!payload.ok||!payload.match){ root.innerHTML=`<div class="card empty-state">${esc(payload.message||'Unable to analyze gap.')}</div>`; return; }
      const m = payload.match;
      const pct = Math.round(Number(m.total_score||0));
      const matched = (m.matched_skills||[]).filter(s=>s.meets_threshold);
      const gaps = (m.skill_gaps||[]).concat((m.missing_skills||[]).map(name=>({name, score:0, min_required:70, meets_threshold:false})));
      root.innerHTML=`<article class="card gap-summary"><span class="eyebrow">COMPATIBILITY</span><h3>${esc(c.name)} → ${esc(o.title)}</h3><div class="gap-score"><strong>${pct}%</strong><span>total match score (server calculated)</span></div><div class="bar large"><i style="width:${pct}%"></i></div><p>${matched.length} verified skills meet required thresholds.</p></article><article class="card"><div class="card-head"><div><h3>Matched Skills</h3><p>Proficiency-aware matches</p></div></div><div class="chips big">${matched.map(s=>`<span class="success-chip">✓ ${esc(s.name)} (${Math.round(s.score)}%)</span>`).join('')||'<span>No threshold matches</span>'}</div></article><article class="card"><div class="card-head"><div><h3>Skill Gaps</h3><p>Below minimum or missing</p></div></div><div class="chips big">${gaps.map(s=>`<span class="warning-chip">+ ${esc(s.name)}${s.score!=null?` (${Math.round(s.score)}% < ${s.min_required||70}%)`:''}</span>`).join('')||'<span class="success-chip">✓ No skill gaps detected</span>'}</div></article>`;
    } catch (err) {
      root.innerHTML='<div class="card empty-state">Unable to load gap analysis.</div>';
    }
  }
  $('#gapOpportunity')?.addEventListener('change',gapRender);$('#gapCandidate')?.addEventListener('change',gapRender);

  function drawChart(canvasId){
    const canvas=document.getElementById(canvasId);if(!canvas)return;const ctx=canvas.getContext('2d'), rect=canvas.getBoundingClientRect(),dpr=window.devicePixelRatio||1;canvas.width=rect.width*dpr;canvas.height=rect.height*dpr;ctx.scale(dpr,dpr);const w=rect.width,h=rect.height,p={l:34,r:12,t:14,b:28};ctx.clearRect(0,0,w,h);
    const points=data.trend||[]; const vals=points.map(x=>Number(x.total||0));const max=Math.max(1,...vals);ctx.strokeStyle='#e8eef7';ctx.lineWidth=1;for(let i=0;i<5;i++){const y=p.t+(h-p.t-p.b)*i/4;ctx.beginPath();ctx.moveTo(p.l,y);ctx.lineTo(w-p.r,y);ctx.stroke();ctx.fillStyle='#8190a8';ctx.font='11px Inter, sans-serif';ctx.fillText(String(Math.round(max-(max*i/4))),5,y+4);}if(!points.length){ctx.fillStyle='#8190a8';ctx.font='13px Inter, sans-serif';ctx.fillText('No application activity recorded yet.',p.l, h/2);return;}
    const innerW=w-p.l-p.r,innerH=h-p.t-p.b, coords=vals.map((v,i)=>({x:p.l+(points.length===1?innerW/2:i*innerW/(points.length-1)),y:p.t+innerH-(v/max)*innerH}));ctx.beginPath();coords.forEach((pt,i)=>i?ctx.lineTo(pt.x,pt.y):ctx.moveTo(pt.x,pt.y));ctx.lineTo(coords[coords.length-1].x,p.t+innerH);ctx.lineTo(coords[0].x,p.t+innerH);ctx.closePath();const g=ctx.createLinearGradient(0,p.t,0,p.t+innerH);g.addColorStop(0,'rgba(37,99,235,.18)');g.addColorStop(1,'rgba(37,99,235,0)');ctx.fillStyle=g;ctx.fill();ctx.beginPath();coords.forEach((pt,i)=>i?ctx.lineTo(pt.x,pt.y):ctx.moveTo(pt.x,pt.y));ctx.strokeStyle='#2563eb';ctx.lineWidth=2.5;ctx.stroke();coords.forEach(pt=>{ctx.beginPath();ctx.arc(pt.x,pt.y,3.5,0,Math.PI*2);ctx.fillStyle='#2563eb';ctx.fill();});ctx.fillStyle='#8190a8';ctx.font='10px Inter, sans-serif';points.forEach((pt,i)=>{if(i===0||i===points.length-1||i%3===0)ctx.fillText(String(pt.day).slice(5),coords[i].x-13,h-7);});
  }
  window.addEventListener('resize',()=>{drawChart('applicationChart');drawChart('analyticsChart');});
  setTimeout(()=>{drawChart('applicationChart');drawChart('analyticsChart');},80);

  $('#globalSearch')?.addEventListener('input',e=>{
    const q=e.target.value.toLowerCase().trim(), panel=$('#searchResults');if(!q){panel.classList.remove('show');panel.innerHTML='';return;}
    const out=[];data.candidates.forEach(c=>{if(`${c.name} ${c.institute_name||c.institute} ${c.skill_text||''}`.toLowerCase().includes(q))out.push({type:'Candidate',title:c.name,meta:c.institute_name||c.institute,go:'candidates'});});data.opportunities.forEach(o=>{if(`${o.title} ${o.type} ${o.location} ${o.mapped_skills||o.required_skills||''}`.toLowerCase().includes(q))out.push({type:o.type,title:o.title,meta:`${o.location||'No location'} • ${o.status}`,go:o.type==='Internship'?'internships':'jobs'});});panel.innerHTML=out.slice(0,8).map((r,i)=>`<button data-search-go="${i}"><small>${esc(r.type)}</small><strong>${esc(r.title)}</strong><span>${esc(r.meta)}</span></button>`).join('')||'<div class="search-empty">No results found</div>';panel.classList.add('show');$$('[data-search-go]',panel).forEach(b=>b.addEventListener('click',()=>{const r=out[Number(b.dataset.searchGo)];activateSection(r.go);panel.classList.remove('show');}));
  });
  document.addEventListener('click',e=>{if(!e.target.closest('.global-search'))$('#searchResults')?.classList.remove('show');});
  $('#newCollabBtn')?.addEventListener('click',()=>openModal('collabModal'));
  $('#newResourceBtn')?.addEventListener('click',()=>openModal('resourceModal'));
  function resetLearningModules() {
    const root = $('#learningModulesList');
    if (!root) return;
    root.innerHTML = '';
  }
  function addLearningModule(title='', description='') {
    const root = $('#learningModulesList');
    if (!root || root.children.length >= 30) return;
    const n = root.children.length + 1;
    const row = document.createElement('div');
    row.className = 'learning-module-editor';
    row.innerHTML = `<div class="learning-module-head"><strong>Module ${n}</strong><button type="button" class="row-remove" title="Remove module">Remove</button></div><div class="form-grid"><label>Module title *<input class="input" name="modules[]" required maxlength="180" value="${esc(title)}" placeholder="e.g. Python Fundamentals"></label><label>Module description<textarea class="input textarea" name="module_descriptions[]" rows="2" placeholder="Topics, practical work and expected outcome">${esc(description)}</textarea></label></div>`;
    row.querySelector('.row-remove')?.addEventListener('click', () => { row.remove(); [...root.children].forEach((el,i)=>{ const h=el.querySelector('.learning-module-head strong'); if(h) h.textContent=`Module ${i+1}`; }); });
    root.appendChild(row);
  }
  $('#addLearningModule')?.addEventListener('click', () => addLearningModule());
  $('#newLearningBtn')?.addEventListener('click', () => {
    $('#learningProgramForm')?.reset();
    if($('#lpId')) $('#lpId').value = '';
    if($('#lpStatus')) $('#lpStatus').value = 'Draft';
    if($('#learningModalTitle')) $('#learningModalTitle').textContent = 'New Learning Program';
    resetLearningModules();
    addLearningModule();
    openModal('learningModal');
  });
  $$('[data-edit-learning]').forEach(b => b.addEventListener('click', () => {
    const lp = (data.learningPrograms || []).find(x => Number(x.id) === Number(b.dataset.editLearning));
    if (!lp) return;
    if($('#lpId')) $('#lpId').value = lp.id;
    if($('#lpTitle')) $('#lpTitle').value = lp.title || '';
    if($('#lpType')) $('#lpType').value = lp.type || 'Training';
    if($('#lpSkills')) $('#lpSkills').value = lp.target_skills || '';
    if($('#lpDuration')) $('#lpDuration').value = lp.duration || '';
    if($('#lpStipend')) $('#lpStipend').value = lp.stipend_or_fee || '';
    if($('#lpLocation')) $('#lpLocation').value = lp.location || '';
    if($('#lpStatus')) $('#lpStatus').value = lp.status || 'Draft';
    if($('#lpDescription')) $('#lpDescription').value = lp.description || '';
    resetLearningModules();
    const mods = Array.isArray(lp.modules) ? lp.modules : [];
    if (mods.length) mods.forEach(m => addLearningModule(m.module_title || '', m.description || ''));
    else addLearningModule();
    if($('#learningModalTitle')) $('#learningModalTitle').textContent = 'Edit Learning Program';
    openModal('learningModal');
  }));
  $('#flash') && setTimeout(()=>$('#flash')?.remove(),4500);
})();

