<?php
require_once 'config.php';
requireLogin();
?>
<li class="nav-item dropdown">
  <a class="nav-link position-relative" href="#" id="notifDropdown" data-bs-toggle="dropdown" aria-expanded="false" title="Notificações">
    <span style="font-size:1.25rem;">🔔</span>
    <span id="notifBadge" class="position-absolute top-0 start-100 translate-middle badge rounded-pill bg-danger" style="display:none;">0</span>
  </a>
  <div class="dropdown-menu dropdown-menu-end p-0 shadow" aria-labelledby="notifDropdown" style="min-width:320px;">
    <div class="list-group list-group-flush" id="notifList">
      <div class="p-3 text-muted small">Carregando...</div>
    </div>
    <div class="d-flex justify-content-between p-2 border-top">
      <button class="btn btn-sm btn-outline-secondary" id="notifRefreshBtn" type="button">Atualizar</button>
      <button class="btn btn-sm btn-primary" id="notifMarkAllBtn" type="button">Marcar todas como lidas</button>
    </div>
  </div>
</li>
<script>
(function(){
  async function fetchJSON(url, opts){ const res=await fetch(url, opts||{}); return res.json(); }
  async function refreshCount(){
    try{
      const data=await fetchJSON('notifications_api.php?action=count');
      const badge=document.getElementById('notifBadge');
      if(data&&data.success){ if(data.count>0){ badge.textContent=data.count; badge.style.display='inline-block'; } else { badge.style.display='none'; } }
    }catch(e){}
  }
  async function loadList(){
    const listEl=document.getElementById('notifList'); listEl.innerHTML='<div class="p-3 text-muted small">Carregando...</div>';
    try{
      const data=await fetchJSON('notifications_api.php?action=list&limit=10');
      if(!data.success){ listEl.innerHTML='<div class="p-3 text-danger small">Falha ao carregar.</div>'; return; }
      if(!data.items||data.items.length===0){ listEl.innerHTML='<div class="p-3 text-muted small">Sem notificações.</div>'; return; }
      listEl.innerHTML='';
      data.items.forEach(item=>{
        const a=document.createElement('a'); a.href='#'; a.className='list-group-item list-group-item-action d-flex justify-content-between align-items-start';
        a.innerHTML='<div class="me-2">'
          + '<div class="fw-semibold ' + (item.status==='unread'?'text-primary':'') + '">' + escapeHtml(item.message) + '</div>'
          + '<div class="small text-muted">' + escapeHtml(item.created_at) + '</div>'
          + '</div>' + (item.status==='unread'?'<span class="badge bg-primary rounded-pill">novo</span>':'');
        a.addEventListener('click', async (ev)=>{
          ev.preventDefault();
          if(item.status==='unread'){
            const fd=new FormData(); fd.append('action','mark_read'); fd.append('id',item.id);
            const res=await fetch('notifications_api.php',{method:'POST',body:fd}); const json=await res.json();
            if(json.success){ await loadList(); await refreshCount(); }
          }
        });
        listEl.appendChild(a);
      });
    }catch(e){ listEl.innerHTML='<div class="p-3 text-danger small">Erro ao carregar.</div>'; }
  }
  function escapeHtml(s){ return String(s).replace(/[&<>"']/g, m=>({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[m])); }
  document.addEventListener('DOMContentLoaded',function(){
    refreshCount(); setInterval(refreshCount,30000);
    const dd=document.getElementById('notifDropdown'); dd&&dd.addEventListener('show.bs.dropdown', loadList);
    const btnRefresh=document.getElementById('notifRefreshBtn'); btnRefresh&&btnRefresh.addEventListener('click', loadList);
    const btnAll=document.getElementById('notifMarkAllBtn'); btnAll&&btnAll.addEventListener('click', async()=>{
      const fd=new FormData(); fd.append('action','mark_all_read');
      const res=await fetch('notifications_api.php',{method:'POST',body:fd}); const json=await res.json();
      if(json.success){ await loadList(); await refreshCount(); }
    });
  });
})();
</script>
