(() => {
  const D = window.INSTITUTE_DATA || {},
    $ = (id) => document.getElementById(id);
  const esc = (v) =>
    String(v ?? "").replace(
      /[&<>"']/g,
      (c) =>
        ({
          "&": "&amp;",
          "<": "&lt;",
          ">": "&gt;",
          '"': "&quot;",
          "'": "&#039;",
        })[c],
    );
  const val = (v, s = "") =>
    v === null || v === undefined || v === "" ? "—" : esc(v) + s;
  const icon = (name) =>
    ({
      user: '<svg viewBox="0 0 24 24" fill="none"><circle cx="12" cy="7" r="4"/><path d="M4 21a8 8 0 0 1 16 0"/></svg>',
      users:
        '<svg viewBox="0 0 24 24" fill="none"><circle cx="9" cy="7" r="4"/><path d="M2 21a7 7 0 0 1 14 0M16 4a4 4 0 0 1 0 7M19 14a5 5 0 0 1 3 4"/></svg>',
      briefcase:
        '<svg viewBox="0 0 24 24" fill="none"><rect x="3" y="7" width="18" height="13" rx="2"/><path d="M8 7V5a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"/></svg>',
      file: '<svg viewBox="0 0 24 24" fill="none"><path d="M6 2h9l5 5v15H6a2 2 0 0 1-2-2V4a2 2 0 0 1 2-2Z"/><path d="M14 2v6h6"/></svg>',
      target:
        '<svg viewBox="0 0 24 24" fill="none"><circle cx="12" cy="12" r="8"/><circle cx="12" cy="12" r="3"/></svg>',
    })[name] || "";
  const sections = {
    dashboard: [
      "Institute Dashboard",
      "Student talent, skill intelligence and industry alignment.",
    ],
    students: [
      "Students",
      "Manage and review student profiles using live institute data.",
    ],
        skills: [
      "Skill Intelligence",
      "Institute talent mapped to the shared SkillBridge skill taxonomy.",
    ],
    profiles: ["Student Profiles", "Detailed student profiles and verified skill evidence."],
    progress: ["Student Progress", "Monitor assessment and skill progress from student evidence."],
    batch: ["Batch / Department Analytics", "Institute-level academic and talent analytics."],
    demand: ["Industry Demand", "Skills and opportunities currently requested by industry."],
    readiness: ["Placement Readiness", "Evidence-based placement readiness from available student data."],
        learning: [
      "Personalized Learning Paths",
      "Recommendations generated from verified skill gaps.",
    ],
    opportunities: [
      "Industry Opportunities",
      "Jobs and internships published by industry.",
    ],
    applications: [
      "Applications & Placement",
      "Institute-side application and placement tracking.",
    ],
    collaboration: [
      "Industry Collaboration",
      "Workshops, drives, projects and research collaboration.",
    ],
    faculty: ["Faculty Dashboard", "Faculty-facing student and placement insights."],
    resources: ["Industry Insights & Resources", "Technical question banks, interview topics, and curriculum recommendations published by companies."],
    reports: ["Reports & Analytics", "Institute reports generated from live database records."],
    notifications: ["Notifications", "Institute notification center."],
    messages: ["Messages", "Institute communication center."],
  };
  function go(s) {
    document
      .querySelectorAll(".nav-item")
      .forEach((b) => b.classList.toggle("active", b.dataset.section === s));
    document
      .querySelectorAll(".page")
      .forEach((p) => p.classList.toggle("active", p.id === s));
    if (sections[s]) {
      $("pageTitle").textContent = sections[s][0];
      $("pageSubtitle").textContent = sections[s][1];
    }
    document.body.classList.remove("sidebar-open");
    window.scrollTo({ top: 0, behavior: "smooth" });
  }
  document
    .querySelectorAll(".nav-item")
    .forEach((b) => b.addEventListener("click", () => go(b.dataset.section)));
  document
    .querySelectorAll("[data-section-link]")
    .forEach((b) => b.addEventListener("click", () => go(b.dataset.section)));
  $("menuBtn")?.addEventListener("click", () => document.body.classList.toggle("sidebar-open"));
  document.querySelectorAll(".nav-item").forEach(b => b.addEventListener("click", () => document.body.classList.remove("sidebar-open")));
  document.addEventListener("click", e => { if(document.body.classList.contains("sidebar-open") && !e.target.closest(".sidebar") && !e.target.closest("#menuBtn")) document.body.classList.remove("sidebar-open"); });
  const metrics = [
    ["students", "Students", "users", "Institute student profiles"],
    ["active_opportunities", "Open Opportunities", "briefcase", "Active jobs and internships"],
    ["applications", "Applications", "file", "Student applications"],
    ["average_match", "Average Match", "target", "Stored opportunity match score"],
  ];
  const metricHtml = metrics
    .map(
      ([k, n, ic, s]) =>
        `<article class="metric"><div class="metric-top"><span>${n}</span><span class="metric-icon">${icon(ic)}</span></div><strong>${val(D.metrics?.[k], k === "average_match" && D.metrics?.[k] !== null ? "%" : "")}</strong><small>${s}</small></article>`,
    )
    .join("");
  $("metricGrid").innerHTML = metricHtml;
  $("analyticsMetrics").innerHTML = metricHtml;
  function renderBars(target, rows, valueKey, labelKey) {
    const el = $(target);
    if (!el) return;
    if (!rows?.length) {
      el.innerHTML =
        '<div class="empty-state compact"><span>—</span><h3>No data available</h3><p>No matching records exist for the selected institute.</p></div>';
      return;
    }
    const max = Math.max(...rows.map((r) => Number(r[valueKey]) || 0), 1);
    el.innerHTML = rows
      .map((r) => {
        const n = Number(r[valueKey]) || 0;
        return `<div class="bar-row"><span title="${esc(r[labelKey])}">${esc(r[labelKey])}</span><div class="bar-track"><div class="bar-fill" style="width:${Math.max(3, (n / max) * 100)}%"></div></div><span class="bar-value">${n}</span></div>`;
      })
      .join("");
  }
  renderBars("skillBars", D.skills, "student_count", "name");
  renderBars("skillBarsFull", D.skills, "student_count", "name");
  renderBars("skillBarsBatch", D.skills, "student_count", "name");
  renderBars("demandBars", D.demand, "opportunity_count", "name");
  renderBars("demandBarsDemand", D.demand, "opportunity_count", "name");
  const statusClass = (s) => {
    const x = String(s || "").toLowerCase();
    return x.includes("select") ||
      x.includes("accept") ||
      x.includes("active") ||
      x.includes("open") ||
      x.includes("publish")
      ? "success"
      : x.includes("reject") || x.includes("close")
        ? "danger"
        : x.includes("pending") || x.includes("interview")
          ? "warning"
          : "neutral";
  };
  const emptyRow = (n, msg = "No data available") =>
    `<tr><td colspan="${n}" class="empty-cell">${msg}</td></tr>`;
  function renderStudents(rows) {
    const el = $("studentsBody");
    if (!el) return;
    $("studentResultCount").textContent = `${rows?.length || 0} student${rows?.length === 1 ? "" : "s"}`;
    if (!rows?.length) { el.innerHTML = emptyRow(6, "No student records available for this institute."); return; }
    el.innerHTML = rows.map(r => {
      const degree = r.degree || r.qualification || "Not provided";
      const branch = r.branch || "Not provided";
      const cgpa = r.cgpa === null || r.cgpa === undefined || r.cgpa === "" ? "Not provided" : Number(r.cgpa).toFixed(2);
      const semester = r.semester === null || r.semester === undefined || r.semester === "" ? "Not provided" : `Sem ${esc(r.semester)}`;
      const skills = r.mapped_skills || r.skills || "No mapped skills";
      return `<tr><td><div class="student-cell"><span class="avatar avatar-soft">${esc(String(r.name || "?").trim().charAt(0).toUpperCase())}</span><div><strong>${esc(r.name || "Unnamed")}</strong><small>${esc(r.email || "Email unavailable")}</small></div></div></td><td><strong>${esc(degree)}</strong><small class="cell-sub">${esc(branch)}</small></td><td>${esc(cgpa)}</td><td>${semester}</td><td><span class="skill-text">${esc(skills)}</span></td><td><button class="view-btn" data-student-id="${esc(r.id)}">View profile ${icon("target")}</button></td></tr>`;
    }).join("");
    document.querySelectorAll("[data-student-id]").forEach(b => b.addEventListener("click", () => openProfile(b.dataset.studentId)));
  }
  renderStudents(D.students || []);
  function fillStudentFilters() {
    const qSel = $("qualificationFilter"), bSel = $("branchFilter");
    const quals = [...new Set((D.students || []).map(r => String(r.degree || r.qualification || "").trim()).filter(Boolean))].sort();
    const branches = [...new Set((D.students || []).map(r => String(r.branch || "").trim()).filter(Boolean))].sort();
    quals.forEach(v => { const o=document.createElement("option"); o.value=v; o.textContent=v; qSel.appendChild(o); });
    branches.forEach(v => { const o=document.createElement("option"); o.value=v; o.textContent=v; bSel.appendChild(o); });
  }
  fillStudentFilters();
  function applyStudentFilters() {
    const q = String($("studentSearch")?.value || "").toLowerCase().trim();
    const qual = String($("qualificationFilter")?.value || "");
    const branch = String($("branchFilter")?.value || "");
    renderStudents((D.students || []).filter(r =>
      (!q || JSON.stringify(r).toLowerCase().includes(q)) &&
      (!qual || String(r.degree || r.qualification || "") === qual) &&
      (!branch || String(r.branch || "") === branch)
    ));
  }
  $("studentSearch")?.addEventListener("input", applyStudentFilters);
  $("qualificationFilter")?.addEventListener("change", applyStudentFilters);
  $("branchFilter")?.addEventListener("change", applyStudentFilters);
  function renderOpps(rows, target) {
    const el = $(target);
    if (!el) return;
    if (!rows?.length) {
      el.innerHTML = emptyRow(target === "dashboardOpps" ? 4 : 6);
      return;
    }
    el.innerHTML = rows
      .map(
        (r) =>
          `<tr><td><strong>${esc(r.title || "—")}</strong></td><td>${esc(r.type || "—")}</td>${target !== "dashboardOpps" ? `<td>${esc(r.department || "—")}</td>` : ""}<td>${esc(r.location || "—")}</td>${target !== "dashboardOpps" ? `<td>${esc(r.deadline || "—")}</td>` : ""}<td><span class="status ${statusClass(r.status)}">${esc(r.status || "—")}</span></td></tr>`,
      )
      .join("");
  }
  renderOpps(D.opportunities, "dashboardOpps");
  renderOpps(D.opportunities, "opportunitiesBody");
  function renderApps(rows, target) {
    const el = $(target);
    if (!el) return;
    if (!rows?.length) {
      el.innerHTML = emptyRow(target === "dashboardApps" ? 3 : 5);
      return;
    }
    el.innerHTML = rows
      .map(
        (r) =>
          `<tr>${target !== "dashboardApps" ? `<td>${esc(r.name || "—")}</td>` : ""}<td>${esc(r.title || "—")}</td>${target !== "dashboardApps" ? `<td>${r.match_score == null ? "—" : esc(r.match_score) + "%"}</td>` : ""}<td><span class="status ${statusClass(r.status)}">${esc(r.status || "—")}</span></td>${target !== "dashboardApps" ? `<td>${esc(r.applied_at || "—")}</td>` : ""}</tr>`,
      )
      .join("");
  }
  renderApps(D.applications, "dashboardApps");
  renderApps(D.applications, "applicationsBody");
  function renderCollab(rows) {
    const el = $("collaborationBody");
    if (!rows?.length) {
      el.innerHTML = emptyRow(5);
      return;
    }
    el.innerHTML = rows
      .map(
        (r) =>
          `<tr><td>${esc(r.company_name || "—")}</td><td>${esc(r.collaboration_type || "—")}</td><td>${esc(r.focus_skills || "—")}</td><td>${esc(r.event_date || "—")}</td><td><span class="status ${statusClass(r.status)}">${esc(r.status || "—")}</span></td></tr>`,
      )
      .join("");
  }
  renderCollab(D.collaboration);
  $("dataHealth").innerHTML = [
    ["Student profiles", D.metrics?.students !== null],
    ["Industry opportunities", D.metrics?.active_opportunities !== null],
    ["Applications", D.metrics?.applications !== null],
    ["Skill mappings", !!D.skills?.length],
    ["Industry demand mapping", !!D.demand?.length],
    ["Verified assessment layer", false],
  ]
    .map(
      ([n, ok]) =>
        `<div class="health-item"><strong>${esc(n)}</strong><span class="health-state ${ok ? "available" : "unavailable"}">${ok ? "Source available" : "No data available"}</span></div>`,
    )
    .join("");
  function renderReadiness() {
    const r = D.readiness || {high:0, moderate:0, developing:0, assessed_students:0};
    const total = Math.max(Number(D.metrics?.students) || 0, 1);
    const rows = [
      ["High readiness (80%+)", Number(r.high)||0],
      ["Moderate (60–79%)", Number(r.moderate)||0],
      ["Developing (<60%)", Number(r.developing)||0]
    ];
    const el=$("readinessBars");
    if(el) el.innerHTML=rows.map(([label,n])=>`<div class="bar-row"><span>${esc(label)}</span><div class="bar-track"><div class="bar-fill" style="width:${Math.min(100,(n/total)*100)}%"></div></div><span class="bar-value">${n}</span></div>`).join("");
    if($("readinessAssessed")) $("readinessAssessed").textContent=Number(r.assessed_students)||0;
    const top=(D.students||[]).filter(x=>x.verified_skill_score!==null && x.verified_skill_score!==undefined && x.verified_skill_score!=="").sort((a,b)=>Number(b.verified_skill_score)-Number(a.verified_skill_score)).slice(0,5);
    const tb=$("readinessStudentsBody");
    if(tb) tb.innerHTML=top.length?top.map(st=>`<tr><td><strong>${esc(st.name||"Unnamed")}</strong></td><td>${esc(st.branch||"Not provided")}</td><td><b>${Number(st.verified_skill_score).toFixed(1)}%</b></td></tr>`).join(""):emptyRow(3,"No verified skill evidence is available yet.");
  }
  renderReadiness();
  function renderMarketInsights(){
    const m=D.market_insights||{};
    const q=Object.entries(m.qualifications||{}).map(([k,v])=>`${esc(k)} (${v})`).join(", ");
    if($("qualificationDemand")) $("qualificationDemand").textContent=q||"No opportunity qualification data available.";
    if($("cgpaDemand")) $("cgpaDemand").textContent=m.avg_min_cgpa?`${m.avg_min_cgpa} / 10.0`:"No CGPA requirement data available.";
    const d=Object.entries(m.domains||{}).map(([k,v])=>`${esc(k)} (${v})`).join(", ");
    if($("domainDemand")) $("domainDemand").textContent=d||"No skill-demand data available.";
  }
  renderMarketInsights();
  $("globalSearch")?.addEventListener("keydown", (e) => {
    if (e.key !== "Enter") return;
    const q = e.target.value.toLowerCase(),
      hit = (D.students || []).some((r) =>
        JSON.stringify(r).toLowerCase().includes(q),
      )
        ? "students"
        : (D.opportunities || []).some((r) =>
              JSON.stringify(r).toLowerCase().includes(q),
            )
          ? "opportunities"
          : (D.skills || []).some((r) =>
                JSON.stringify(r).toLowerCase().includes(q),
              )
            ? "skills"
            : null;
    if (hit) go(hit);
  });
  const modal = $("studentModal"),
    content = $("profileContent");
  $("modalClose")?.addEventListener("click", closeProfile);
  modal?.addEventListener("click", (e) => {
    if (e.target === modal) closeProfile();
  });
  document.addEventListener("keydown", (e) => {
    if (e.key === "Escape") closeProfile();
  });
  function closeProfile() {
    modal.classList.remove("open");
    modal.setAttribute("aria-hidden", "true");
    document.body.classList.remove("modal-open");
  }
  async function openProfile(id) {
    modal.classList.add("open");
    modal.setAttribute("aria-hidden", "false");
    document.body.classList.add("modal-open");
    content.innerHTML =
      '<div class="profile-loading">Loading student profile...</div>';
    try {
      const res = await fetch(
        `${window.STUDENT_API}?id=${encodeURIComponent(id)}&institute_id=${encodeURIComponent(window.SELECTED_INSTITUTE_ID || 0)}`,
        { headers: { Accept: "application/json" } },
      );
      const data = await res.json();
      if (!res.ok || !data.ok)
        throw new Error(data.message || "Unable to load profile");
      renderProfile(data);
    } catch (e) {
      content.innerHTML = `<div class="profile-error"><strong>Profile unavailable</strong><p>${esc(e.message)}</p><button class="secondary-btn" onclick="document.getElementById('modalClose').click()">Close</button></div>`;
    }
  }
  function renderProfile(data) {
    const s = data.student || {},
      skills = data.skills || [],
      apps = data.applications || [];
    const initials = String(s.name || "?")
      .trim()
      .split(/\s+/)
      .slice(0, 2)
      .map((x) => x[0])
      .join("")
      .toUpperCase();
    const verifiedAvg = skills.filter(k => k.verified && k.score != null).reduce((a,k)=>a+Number(k.score),0) / Math.max(1,skills.filter(k => k.verified && k.score != null).length);
    const verifiedLabel = skills.some(k => k.verified && k.score != null) ? `${verifiedAvg.toFixed(1)}%` : "Not assessed";
    content.innerHTML = `<div class="profile-top"><div class="profile-avatar">${esc(initials)}</div><div class="profile-main"><div class="eyebrow">STUDENT PROFILE</div><h2 id="profileName">${esc(s.name || "Unnamed Student")}</h2><p>${esc(s.degree || s.qualification || "Student")} ${s.branch ? "• " + esc(s.branch) : ""}</p><div class="profile-meta"><span>${icon("user")}${esc(s.institute_name || "Institute unavailable")}</span><span>${s.location ? esc(s.location) : "Location unavailable"}</span></div></div><div class="profile-score"><small>Verified skill score</small><strong>${verifiedLabel}</strong></div></div><div class="profile-grid"><div class="profile-section"><h3>Contact & Academic</h3><div class="detail-list"><div><span>Email</span><strong>${esc(s.email || "Not available")}</strong></div><div><span>Phone</span><strong>${esc(s.phone || "Not available")}</strong></div><div><span>Degree</span><strong>${esc(s.degree || s.qualification || "Not available")}</strong></div><div><span>Branch</span><strong>${esc(s.branch || "Not available")}</strong></div><div><span>Semester</span><strong>${s.semester == null ? "Not available" : esc(s.semester)}</strong></div><div><span>CGPA</span><strong>${s.cgpa == null ? "Not available" : Number(s.cgpa).toFixed(2)}</strong></div><div><span>Graduation</span><strong>${esc(s.graduation_year || "Not available")}</strong></div><div><span>Career Interest</span><strong>${esc(s.career_interest || "Not available")}</strong></div></div></div><div class="profile-section"><h3>Skills & Verification</h3><div class="profile-skills">${skills.length ? skills.map((k) => `<span class="skill-chip"><strong>${esc(k.name)}</strong>${k.score != null ? `<small>${Number(k.score).toFixed(0)}%</small>` : k.proficiency != null ? `<small>${esc(k.proficiency)}</small>` : ""}</span>`).join("") : '<div class="mini-empty">No mapped skills available.</div>'}</div></div></div><div class="profile-section"><h3>About</h3><p class="bio">${esc(s.bio || "No profile summary available.")}</p></div><div class="profile-section"><div class="section-row"><h3>Applications</h3><span class="muted-count">${apps.length} record${apps.length === 1 ? "" : "s"}</span></div>${apps.length ? `<div class="mini-table"><table><thead><tr><th>Opportunity</th><th>Match</th><th>Status</th><th>Applied</th></tr></thead><tbody>${apps.map((a) => `<tr><td>${esc(a.title || "—")}</td><td>${a.match_score == null ? "—" : esc(a.match_score) + "%"}</td><td><span class="status ${statusClass(a.status)}">${esc(a.status || "—")}</span></td><td>${esc(a.applied_at || "—")}</td></tr>`).join("")}</tbody></table></div>` : '<div class="mini-empty">No application records available.</div>'}</div>`;
  }
  // Opportunity Eligibility & Placement Coordination Engine
  async function renderOpportunityEligibility() {
    const oppId = Number($("coordinationOppSelect")?.value || 0), root = $("eligibilityResultArea");
    if (!root) return;
    if (!oppId) { root.innerHTML = '<div class="card empty-state">Select an industry opportunity to compare its requirements with this institute\'s student records.</div>'; return; }
    root.innerHTML = '<div class="card loading-state">Checking current student records and opportunity requirements…</div>';
    try {
      const res = await fetch(`api/eligibility.php?opportunity_id=${encodeURIComponent(oppId)}`, {headers:{Accept:"application/json"}, credentials:"same-origin"});
      const data = await res.json();
      if(!res.ok || !data.ok) throw new Error(data.message || "Eligibility data unavailable");
      const eligible=data.eligible||[], ineligible=data.ineligible||[], o=data.opportunity||{};
      const cgpa = Number(o.min_cgpa||0);
      const reqQual = o.qualification ? esc(o.qualification) : "Not specified";
      const branches = o.allowed_branches ? esc(o.allowed_branches) : "Any branch / not specified";
      const skills = Array.isArray(o.required_skills) ? o.required_skills.map(esc).join(", ") : esc(o.required_skills||"");
      root.innerHTML=`
        <div class="card" style="margin-bottom:20px;padding:18px 20px;background:#f8fafc;">
          <div class="card-head" style="margin-bottom:12px;"><div><h3>Eligibility Rules for ${esc(o.title||"Selected Opportunity")}</h3><p>Students are eligible only when every requirement configured by the industry is satisfied.</p></div></div>
          <div class="grid-2" style="gap:10px;">
            <div class="dynamic-insight"><strong>Qualification</strong><span>${reqQual}</span></div>
            <div class="dynamic-insight"><strong>Allowed Branches</strong><span>${branches}</span></div>
            <div class="dynamic-insight"><strong>Minimum CGPA</strong><span>${cgpa>0 ? cgpa.toFixed(2) : "No minimum specified"}</span></div>
            <div class="dynamic-insight"><strong>Required Skills</strong><span>${skills || "No skill requirement specified"}</span></div>
          </div>
        </div>
        <div class="grid-2" style="margin-bottom:20px;">
          <div class="card eligibility-card eligible-card"><small>ELIGIBLE CANDIDATES</small><strong>${eligible.length} Students</strong><span>All configured eligibility requirements are satisfied.</span></div>
          <div class="card eligibility-card ineligible-card"><small>NOT ELIGIBLE CANDIDATES</small><strong>${ineligible.length} Students</strong><span>Each row below shows the specific requirement(s) that are not satisfied.</span></div>
        </div>
        <div class="card table-card">
          <div class="card-head"><div><h3>Eligible Students (${eligible.length})</h3><p>Live student records for the selected institute.</p></div></div>
          <div class="table-scroll"><table><thead><tr><th>Student</th><th>Degree / Branch</th><th>CGPA</th><th>Mapped Skills</th><th>Status</th></tr></thead><tbody>
            ${eligible.length?eligible.map(s=>`<tr><td><strong>${esc(s.name)}</strong><small class="cell-sub">${esc(s.email||"")}</small></td><td>${esc(s.degree||"Not provided")} / ${esc(s.branch||"Not provided")}</td><td>${s.cgpa===null?"Not provided":esc(Number(s.cgpa).toFixed(2))}</td><td><span class="skill-text">${esc(s.skills||"No mapped skills")}</span></td><td><span class="status success">Eligible</span></td></tr>`).join(""):emptyRow(5,"No students currently satisfy every configured requirement.")}
          </tbody></table></div>
        </div>
        <div class="card table-card" style="margin-top:20px;">
          <div class="card-head"><div><h3>Not Eligible Students (${ineligible.length})</h3><p>Reasons are calculated from the selected opportunity and each student's current academic and skill records.</p></div></div>
          <div class="table-scroll"><table><thead><tr><th>Student</th><th>Degree / Branch</th><th>CGPA</th><th>Missing / Mismatched Requirements</th></tr></thead><tbody>
            ${ineligible.length?ineligible.map(x=>`<tr><td><strong>${esc(x.student.name)}</strong><small class="cell-sub">${esc(x.student.email||"")}</small></td><td>${esc(x.student.degree||"Not provided")} / ${esc(x.student.branch||"Not provided")}</td><td>${x.student.cgpa===null?"Not provided":esc(Number(x.student.cgpa).toFixed(2))}</td><td>${(x.reasons||[]).map(r=>`<span class="reason-chip">${esc(r)}</span>`).join(" ")}</td></tr>`).join(""):emptyRow(4,"All institute students satisfy the configured criteria.")}
          </tbody></table></div>
        </div>`;
    } catch(e) { root.innerHTML=`<div class="card empty-state"><h3>Eligibility unavailable</h3><p>${esc(e.message)}</p></div>`; }
  }
  $("coordinationOppSelect")?.addEventListener("change", renderOpportunityEligibility);
})();
