(() => {
  const toastEl = document.getElementById('toast');
  const viewport = document.getElementById('canvas-viewport');
  const world = document.getElementById('world');
  const overlay = document.getElementById('arrow-overlay');

  const toast = (msg) => {
    if (!toastEl) return;
    toastEl.textContent = msg;
    toastEl.classList.add('show');
    clearTimeout(toastEl._timer);
    toastEl._timer = setTimeout(() => toastEl.classList.remove('show'), 1200);
  };

  const api = async (action, payload = {}) => {
    const res = await fetch('api.php', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ action, ...payload })
    });
    const data = await res.json();
    if (!res.ok || !data.ok) throw new Error(data.error || `HTTP ${res.status}`);
    return data;
  };

  const state = {
    selectedFactId: null,
    scale: 1,
    tx: 60,
    ty: 30,
    panning: false,
    panStartX: 0,
    panStartY: 0,
    dragStepId: null
  };


  const syncLaneLayout = () => {
    const rows = Array.from(document.querySelectorAll('.turn-row'));
    if (!rows.length) return;

    const measureLane = (selector) => {
      let maxCount = 1;
      rows.forEach((row) => {
        const count = row.querySelectorAll(selector).length;
        maxCount = Math.max(maxCount, count || 1);
      });
      return maxCount;
    };

    const ifCount = measureLane('.lane-if .if-node');
    const reasonCount = measureLane('.lane-reason .reason-card');

    const cardWidth = 210;
    const laneInnerGap = 16;
    const laneGap = 210;
    const factWidth = 420;

    const laneWidthByCount = (count) => (count * cardWidth) + (Math.max(0, count - 1) * laneInnerGap);
    const ifWidth = Math.max(factWidth, laneWidthByCount(ifCount));
    const reasonWidth = Math.max(factWidth, laneWidthByCount(reasonCount));
    const sideWidth = Math.max(ifWidth, reasonWidth);

    document.querySelectorAll('.turn-row, .lane-heads').forEach((el) => {
      el.style.setProperty('--side-lane-width', `${sideWidth}px`);
      el.style.setProperty('--fact-lane-width', `${factWidth}px`);
      el.style.setProperty('--lane-gap', `${laneGap}px`);
      el.style.setProperty('--if-head-width', `${ifWidth}px`);
      el.style.setProperty('--fact-head-width', `${factWidth}px`);
      el.style.setProperty('--reason-head-width', `${reasonWidth}px`);
    });
  };

  const applyTransform = () => {
    if (!world) return;
    world.style.transform = `translate(${state.tx}px, ${state.ty}px) scale(${state.scale})`;
    recalcArrows();
  };

  const setSelectedFact = (id) => {
    state.selectedFactId = id;
    document.querySelectorAll('.fact-node').forEach((el) => {
      const sid = Number(el.dataset.stepInstId || 0);
      el.classList.toggle('selected', sid === id);
    });
  };

  const toggleSelectedFact = (id) => {
    if (state.selectedFactId === id) setSelectedFact(null);
    else setSelectedFact(id);
  };

  const hookCanvasPanZoom = () => {
    if (!viewport) return;

    viewport.addEventListener('pointerdown', (e) => {
      const target = e.target;
      if (target.closest('.card') && !(e.buttons === 4 || e.button === 1 || e.shiftKey || e.code === 'Space')) return;

      if (e.button === 1 || e.shiftKey || e.target === viewport) {
        state.panning = true;
        viewport.classList.add('panning');
        state.panStartX = e.clientX - state.tx;
        state.panStartY = e.clientY - state.ty;
      }
    });

    window.addEventListener('pointermove', (e) => {
      if (!state.panning) return;
      state.tx = e.clientX - state.panStartX;
      state.ty = e.clientY - state.panStartY;
      applyTransform();
    });

    window.addEventListener('pointerup', () => {
      state.panning = false;
      viewport?.classList.remove('panning');
    });

    viewport.addEventListener('wheel', (e) => {
      if (!e.ctrlKey) return;
      e.preventDefault();
      const oldScale = state.scale;
      const next = Math.max(0.35, Math.min(2.2, oldScale * (e.deltaY < 0 ? 1.1 : 0.9)));
      const rect = viewport.getBoundingClientRect();
      const cx = e.clientX - rect.left;
      const cy = e.clientY - rect.top;
      state.tx = cx - ((cx - state.tx) / oldScale) * next;
      state.ty = cy - ((cy - state.ty) / oldScale) * next;
      state.scale = next;
      applyTransform();
    }, { passive: false });

    applyTransform();
  };

  const hookAutoSave = () => {
    const debounceMap = new Map();
    const mark = (el, cls) => {
      el.classList.remove('mark-saving', 'mark-ok', 'mark-err');
      if (cls) el.classList.add(cls);
      if (cls && cls !== 'mark-saving') setTimeout(() => el.classList.remove(cls), 650);
    };

    document.querySelectorAll('[data-autosave="1"]').forEach((el) => {
      const save = async () => {
        const type = el.dataset.type;
        const id = Number(el.dataset.id);
        const field = el.dataset.field;
        const submissionId = Number(el.dataset.submissionId);

        try {
          mark(el, 'mark-saving');
          if (type === 'step') await api('update_step', { submission_id: submissionId, step_inst_id: id, fields: { [field]: el.value } });
          if (type === 'opt') await api('update_option', { submission_id: submissionId, alt_choice_id: id, fields: { [field]: el.value } });
          if (type === 'sub') await api('set_submission_status', { submission_id: submissionId, status: Number(el.value) });
          mark(el, 'mark-ok');
          toast('保存しました');
          recalcArrows();
        } catch (err) {
          mark(el, 'mark-err');
          toast(`保存失敗: ${err.message}`);
        }
      };
      const handler = () => {
        clearTimeout(debounceMap.get(el));
        debounceMap.set(el, setTimeout(save, 350));
      };
      el.addEventListener('input', handler);
      el.addEventListener('change', handler);
    });
  };

  const refresh = () => { location.href = new URL(location.href).toString(); };

  const hookButtons = () => {
    document.querySelectorAll('[data-action]').forEach((btn) => {
      btn.addEventListener('click', async () => {
        const action = btn.dataset.action;
        const submissionId = Number(btn.dataset.submissionId || window.__APP__.submissionId);
        try {
          if (action === 'add-step') {
            const stepDefId = Number(document.getElementById('add-step-def')?.value || window.__APP__.defaultStepDefId || 0);
            await api('add_step', { submission_id: submissionId, step_def_id: stepDefId });
            return refresh();
          }

          if (action === 'delete-step') {
            if (!confirm('この Fact Step を削除しますか？')) return;
            await api('delete_step', { submission_id: submissionId, step_inst_id: Number(btn.dataset.stepInstId) });
            return refresh();
          }
          if (action === 'add-option') {
            const stepInstId = Number(btn.dataset.stepInstId);
            const choiceDefId = Number(document.getElementById(`add-choice-def-${stepInstId}`)?.value || window.__APP__.defaultChoiceDefId || 0);
            await api('add_option', { submission_id: submissionId, step_inst_id: stepInstId, choice_def_id: choiceDefId });
            return refresh();
          }
          if (action === 'select-option') {
            await api('select_option', { submission_id: submissionId, alt_choice_id: Number(btn.dataset.altChoiceId) });
            return refresh();
          }
          if (action === 'delete-option') {
            if (!confirm('この Option を削除しますか？')) return;
            await api('delete_option', { submission_id: submissionId, alt_choice_id: Number(btn.dataset.altChoiceId) });
            return refresh();
          }
        } catch (err) {
          toast(err.message);
        }
      });
    });
  };

  const reorderFromDom = async () => {
    const ids = Array.from(document.querySelectorAll('.fact-node')).map((n) => Number(n.dataset.stepInstId)).filter(Boolean);
    await api('reorder_steps', { submission_id: Number(window.__APP__.submissionId), ordered_step_inst_ids: ids });
  };

  const hookFactInteractions = () => {
    document.querySelectorAll('.fact-node').forEach((fact) => {
      fact.addEventListener('click', (e) => {
        if (e.target.closest('button,input,textarea,select,label')) return;
        toggleSelectedFact(Number(fact.dataset.stepInstId));
      });

      fact.addEventListener('dragstart', (e) => {
        state.dragStepId = Number(fact.dataset.stepInstId);
        e.dataTransfer.effectAllowed = 'move';
      });
      fact.addEventListener('dragover', (e) => e.preventDefault());
      fact.addEventListener('drop', async (e) => {
        e.preventDefault();
        const dragged = document.querySelector(`.fact-node[data-step-inst-id="${state.dragStepId}"]`);
        if (!dragged || dragged === fact) return;
        const parent = fact.parentElement;
        parent.insertBefore(dragged, fact);
        try {
          await reorderFromDom();
          refresh();
        } catch (err) { toast(err.message); }
      });
    });

    document.querySelectorAll('[data-hot-add]').forEach((btn) => {
      btn.addEventListener('click', async (e) => {
        e.stopPropagation();
        const dir = btn.dataset.hotAdd;
        const stepInstId = Number(btn.dataset.stepInstId);
        const submissionId = Number(window.__APP__.submissionId);
        const stepNodes = Array.from(document.querySelectorAll('.fact-node'));
        const idx = stepNodes.findIndex((n) => Number(n.dataset.stepInstId) === stepInstId);
        if (idx < 0) return;

        try {
          if (dir === 'left') {
            const choiceDefId = Number(window.__APP__.defaultChoiceDefId || 0);
            await api('add_option', { submission_id: submissionId, step_inst_id: stepInstId, choice_def_id: choiceDefId });
            return refresh();
          }

          if (dir === 'right') {
            const choiceDefId = Number(window.__APP__.defaultChoiceDefId || 0);
            const added = await api('add_option', { submission_id: submissionId, step_inst_id: stepInstId, choice_def_id: choiceDefId });
            await api('select_option', { submission_id: submissionId, alt_choice_id: Number(added.alt_choice_id) });
            return refresh();
          }

          if (dir === 'down' || dir === 'up') {
            const baseDefId = Number(stepNodes[idx].dataset.stepDefId || window.__APP__.defaultStepDefId || 0);
            const added = await api('add_step', { submission_id: submissionId, step_def_id: baseDefId });
            const newId = Number(added.step_inst_id);

            const order = stepNodes.map((n) => Number(n.dataset.stepInstId));
            if (dir === 'down') order.splice(idx + 1, 0, newId);
            if (dir === 'up') order.splice(idx, 0, newId);
            await api('reorder_steps', { submission_id: submissionId, ordered_step_inst_ids: order });
            return refresh();
          }
        } catch (err) {
          toast(err.message);
        }
      });
    });
  };

  const curvePath = (x1, y1, x2, y2, color, width = 2, dashed = false) => {
    const delta = Math.abs(x2 - x1) * 0.45;
    return `<path d="M ${x1} ${y1} C ${x1 + delta} ${y1}, ${x2 - delta} ${y2}, ${x2} ${y2}" fill="none" stroke="${color}" stroke-width="${width}" ${dashed ? 'stroke-dasharray="6 4"' : ''} marker-end="url(#arr-${color.replace('#', '')})" />`;
  };

  const curvePathVertical = (x1, y1, x2, y2, color, width = 2, dashed = false) => {
    const delta = Math.abs(y2 - y1) * 0.45;
    return `<path d="M ${x1} ${y1} C ${x1} ${y1 + delta}, ${x2} ${y2 - delta}, ${x2} ${y2}" fill="none" stroke="${color}" stroke-width="${width}" ${dashed ? 'stroke-dasharray="6 4"' : ''} marker-end="url(#arr-${color.replace('#', '')})" />`;
  };

  const recalcArrows = () => {
    if (!overlay || !world) return;

    const wr = world.getBoundingClientRect();
    const markers = ['4da3ff', 'ffca5f']
      .map((c) => `<marker id="arr-${c}" markerWidth="12" markerHeight="12" refX="9" refY="4" orient="auto"><path d="M0,0 L0,8 L10,4 z" fill="#${c}"/></marker>`)
      .join('');
    let svg = `<defs>${markers}</defs>`;

    const facts = Array.from(document.querySelectorAll('.fact-node'));
    for (let i = 0; i < facts.length - 1; i++) {
      const a = facts[i].getBoundingClientRect();
      const b = facts[i + 1].getBoundingClientRect();
      svg += curvePath(
        a.left - wr.left + a.width / 2,
        a.bottom - wr.top,
        b.left - wr.left + b.width / 2,
        b.top - wr.top,
        '#4da3ff',
        3.8,
        false
      );
    }

    document.querySelectorAll('.if-node[data-prev-step]').forEach((ifNode) => {
      const prev = Number(ifNode.dataset.prevStep || 0);
      if (!prev) return;
      const prevFact = document.getElementById(`step-${prev}`);
      if (!prevFact) return;
      const a = prevFact.getBoundingClientRect();
      const b = ifNode.getBoundingClientRect();
      svg += curvePathVertical(
        a.left - wr.left + a.width / 2,
        a.bottom - wr.top,
        b.left - wr.left + b.width / 2,
        b.top - wr.top,
        '#ffca5f',
        2.8,
        true
      );
    });

    document.querySelectorAll('.reason-card[data-reason-target-step]').forEach((reason) => {
      const target = Number(reason.dataset.reasonTargetStep || 0);
      if (!target) return;
      const fact = document.getElementById(`step-${target}`);
      if (!fact) return;
      const a = reason.getBoundingClientRect();
      const b = fact.getBoundingClientRect();
      svg += curvePath(
        a.left - wr.left,
        a.top - wr.top + a.height / 2,
        b.right - wr.left,
        b.top - wr.top + b.height / 2,
        '#ffca5f',
        2.8,
        true
      );
    });

    overlay.innerHTML = svg;
  };

  syncLaneLayout();
  hookCanvasPanZoom();
  hookAutoSave();
  hookButtons();
  hookFactInteractions();
  recalcArrows();

  let raf = null;
  const schedule = () => {
    if (raf) return;
    raf = requestAnimationFrame(() => {
      raf = null;
      syncLaneLayout();
      recalcArrows();
    });
  };
  window.addEventListener('resize', schedule);
  if (window.MutationObserver && world) new MutationObserver(schedule).observe(world, { childList: true, subtree: true, attributes: true });
})();
