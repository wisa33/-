(() => {
  const toastEl = document.getElementById('toast');
  const viewport = document.getElementById('canvas-viewport');
  const world = document.getElementById('world');
  const overlay = document.getElementById('arrow-overlay');
  const turnStack = document.getElementById('turn-stack');
  const laneHeads = document.querySelector('.lane-heads');
  const videoBg = document.getElementById('video-bg');
  const ytPlayBtn = document.getElementById('yt-play');
  const ytPauseBtn = document.getElementById('yt-pause');
  const ytRwBtn = document.getElementById('yt-rw');
  const ytFfBtn = document.getElementById('yt-ff');
  const ytSlowBtn = document.getElementById('yt-slow');
  const ytSlowBackBtn = document.getElementById('yt-slow-back');
  const ytStateEl = document.getElementById('yt-state');
  const inputModeBtn = document.getElementById('input-mode-toggle');

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
    dragStepId: null,
    ytPlayer: null,
    ytReady: false,
    ytPlaying: false,
    lastCenteredStepId: null
    ,slowBackTimer: null
  };

  const viewStateKey = `analyzer:view:${Number(window.__APP__?.submissionId || 0)}`;
  const videoStateKey = `analyzer:video:${Number(window.__APP__?.submissionId || 0)}`;
  const inputModeKey = `analyzer:input-mode:${Number(window.__APP__?.submissionId || 0)}`;

  const syncInputModeUi = () => {
    if (!inputModeBtn) return;
    const on = document.body.classList.contains('input-mode');
    inputModeBtn.textContent = on ? '入力モード解除' : '入力モード';
  };

  const setInputMode = (on) => {
    document.body.classList.toggle('input-mode', on);
    try {
      sessionStorage.setItem(inputModeKey, on ? '1' : '0');
    } catch (_) {
      // noop
    }
    syncInputModeUi();
  };

  const initInputMode = () => {
    let on = false;
    try {
      on = sessionStorage.getItem(inputModeKey) === '1';
    } catch (_) {
      on = false;
    }
    setInputMode(on);
    inputModeBtn?.addEventListener('click', () => {
      setInputMode(!document.body.classList.contains('input-mode'));
    });
  };

  const loadViewState = () => {
    try {
      const raw = sessionStorage.getItem(viewStateKey);
      if (!raw) return;
      const parsed = JSON.parse(raw);
      const scale = Number(parsed?.scale);
      const tx = Number(parsed?.tx);
      const ty = Number(parsed?.ty);
      if (Number.isFinite(scale) && scale >= 0.35 && scale <= 2.2) state.scale = scale;
      if (Number.isFinite(tx)) state.tx = tx;
      if (Number.isFinite(ty)) state.ty = ty;
    } catch (_) {
      // noop
    }
  };

  const saveViewState = () => {
    try {
      sessionStorage.setItem(viewStateKey, JSON.stringify({
        scale: state.scale,
        tx: state.tx,
        ty: state.ty
      }));
    } catch (_) {
      // noop
    }
  };

  const saveVideoState = () => {
    if (!state.ytPlayer || !state.ytReady) return;
    try {
      const YT = window.YT;
      const playerState = Number(state.ytPlayer.getPlayerState?.() ?? -1);
      const currentTime = Number(state.ytPlayer.getCurrentTime?.() ?? 0);
      const playbackRate = Number(state.ytPlayer.getPlaybackRate?.() ?? 1);
      sessionStorage.setItem(videoStateKey, JSON.stringify({
        t: currentTime,
        rate: playbackRate,
        shouldPlay: Boolean(YT && playerState === YT.PlayerState.PLAYING)
      }));
    } catch (_) {
      // noop
    }
  };

  const consumeVideoState = () => {
    try {
      const raw = sessionStorage.getItem(videoStateKey);
      if (!raw) return null;
      sessionStorage.removeItem(videoStateKey);
      return JSON.parse(raw);
    } catch (_) {
      return null;
    }
  };

  const updateExpandedRows = () => {
    if (!turnStack) return;
    turnStack.querySelectorAll('.turn-row.row-expanded, .reason-bridge-row.row-expanded').forEach((row) => row.classList.remove('row-expanded'));
    turnStack.querySelectorAll('.turn-row, .reason-bridge-row').forEach((row) => {
      const active = row.querySelector('.fact-node.selected, .interactive-card:focus-within, .reason-empty.editing');
      if (active) row.classList.add('row-expanded');
    });
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
    const factCount = measureLane('.lane-fact .fact-node');

    const cardWidth = 210;
    const cardHeight = 140;
    const laneInnerGap = 12;
    const laneGap = 90;
    const factWidth = 420;
    const bridgeWidth = 240;

    const laneWidthByCount = (count) => (count * cardWidth) + (Math.max(0, count - 1) * laneInnerGap);
    const laneHeadHeightByCount = (count) => (count * cardHeight) + (Math.max(0, count - 1) * 12);
    const ifWidth = Math.max(factWidth, laneWidthByCount(ifCount));
    const turnColWidth = Math.max(factWidth, ifWidth);
    const ifHeadHeight = Math.max(120, laneHeadHeightByCount(ifCount));
    const factHeadHeight = Math.max(120, laneHeadHeightByCount(factCount));

    document.querySelectorAll('.turn-row, .lane-heads, .reason-bridge-row').forEach((el) => {
      el.style.setProperty('--turn-col-width', `${turnColWidth}px`);
      el.style.setProperty('--bridge-col-width', `${bridgeWidth}px`);
      el.style.setProperty('--lane-gap', `${laneGap}px`);
      el.style.setProperty('--if-head-height', `${ifHeadHeight}px`);
      el.style.setProperty('--fact-head-height', `${factHeadHeight}px`);
    });

    updateWorldBounds();
  };

  const updateWorldBounds = () => {
    if (!world || !turnStack) return;
    const padX = 600;
    const padY = 500;
    const contentWidth = turnStack.offsetLeft + turnStack.scrollWidth + padX;
    const contentHeight = turnStack.offsetTop + turnStack.scrollHeight + padY;
    world.style.width = `${Math.max(2600, contentWidth)}px`;
    world.style.minHeight = `${Math.max(1800, contentHeight)}px`;
  };

  const syncLaneHeadPositions = () => {
    if (!viewport || !laneHeads) return;
    const firstTurn = document.querySelector('.turn-row[data-turn]');
    if (!firstTurn) return;

    const factLane = firstTurn.querySelector('.lane-fact');
    const ifLane = firstTurn.querySelector('.lane-if');
    const factHead = laneHeads.querySelector('.lane-head.fact');
    const ifHead = laneHeads.querySelector('.lane-head.if');
    if (!factLane || !ifLane || !factHead || !ifHead) return;

    const vp = viewport.getBoundingClientRect();
    const factRect = factLane.getBoundingClientRect();
    const ifRect = ifLane.getBoundingClientRect();

    laneHeads.style.left = `12px`;

    const clampTop = (desiredTop, h) => {
      const min = 8;
      const max = Math.max(min, vp.height - h - 8);
      return Math.max(min, Math.min(max, desiredTop));
    };

    const fitHeight = (laneHeight) => Math.max(120, Math.min(vp.height - 16, laneHeight));
    const factHeadHeight = fitHeight(factRect.height);
    const ifHeadHeight = fitHeight(ifRect.height);

    factHead.style.height = `${factHeadHeight}px`;
    ifHead.style.height = `${ifHeadHeight}px`;

    const factTop = clampTop((factRect.top - vp.top) + ((factRect.height - factHeadHeight) / 2), factHeadHeight);
    const ifTop = clampTop((ifRect.top - vp.top) + ((ifRect.height - ifHeadHeight) / 2), ifHeadHeight);
    const gap = Math.max(8, ifTop - factTop - factHeadHeight);

    laneHeads.style.top = `0px`;
    laneHeads.style.gap = `${gap}px`;
    factHead.style.marginTop = `${factTop}px`;
    ifHead.style.marginTop = `0px`;
  };

  const applyTransform = () => {
    if (!world) return;
    world.style.transform = `translate(${state.tx}px, ${state.ty}px) scale(${state.scale})`;
    saveViewState();
    syncLaneHeadPositions();
    recalcArrows();
  };

  const centerFactNode = (factNode) => {
    if (!viewport || !factNode) return;
    const vp = viewport.getBoundingClientRect();
    const fr = factNode.getBoundingClientRect();
    const dx = (vp.left + vp.width / 2) - (fr.left + fr.width / 2);
    const dy = (vp.top + vp.height / 2) - (fr.top + fr.height / 2);
    state.tx += dx;
    state.ty += dy;
    applyTransform();
  };

  const initYouTubeBackground = () => {
    const videoId = (window.__APP__.youtubeVideoId || '').trim();
    const setVideoUi = (enabled, text) => {
      if (ytPlayBtn) ytPlayBtn.disabled = !enabled;
      if (ytPauseBtn) ytPauseBtn.disabled = !enabled;
      if (ytRwBtn) ytRwBtn.disabled = !enabled;
      if (ytFfBtn) ytFfBtn.disabled = !enabled;
      if (ytSlowBtn) ytSlowBtn.disabled = !enabled;
      if (ytSlowBackBtn) ytSlowBackBtn.disabled = !enabled;
      if (ytStateEl) ytStateEl.textContent = text;
    };

    const stopSlowBack = () => {
      if (state.slowBackTimer) {
        clearInterval(state.slowBackTimer);
        state.slowBackTimer = null;
      }
      if (ytSlowBackBtn) ytSlowBackBtn.textContent = '0.5x ◀';
    };

    if (!videoBg || videoId === '') {
      if (videoBg) videoBg.style.display = 'none';
      setVideoUi(false, '動画: YouTube URL未設定');
      return;
    }

    setVideoUi(false, '動画: 接続中...');

    const startMonitor = () => {
      window.setInterval(() => {
        if (!state.ytPlayer || !state.ytReady || !state.ytPlaying) return;
        const current = Number(state.ytPlayer.getCurrentTime?.() || 0);
        let candidate = null;
        document.querySelectorAll('.fact-node').forEach((fact) => {
          const tsInput = fact.querySelector('input[data-field="timestamp_sec"]');
          if (!tsInput) return;
          const ts = Number(tsInput.value);
          if (!Number.isFinite(ts) || ts <= 0) return;
          if (ts <= current && (!candidate || ts > candidate.ts)) {
            candidate = { ts, stepId: Number(fact.dataset.stepInstId || 0), node: fact };
          }
        });
        if (!candidate) return;
        if (state.lastCenteredStepId === candidate.stepId) return;
        state.lastCenteredStepId = candidate.stepId;
        centerFactNode(candidate.node);
      }, 250);
    };

    const boot = () => {
      const YT = window.YT;
      if (!YT || !YT.Player) return;
      state.ytPlayer = new YT.Player('yt-player', {
        videoId,
        playerVars: {
          playsinline: 1,
          rel: 0,
          modestbranding: 1,
          controls: 1,
          mute: 1
        },
        events: {
          onReady: () => {
            state.ytReady = true;
            setVideoUi(true, '動画: 停止中');

            const restored = consumeVideoState();
            if (restored) {
              const t = Number(restored.t ?? 0);
              const rate = Number(restored.rate ?? 1);
              const shouldPlay = Boolean(restored.shouldPlay);
              if (Number.isFinite(t) && t > 0) {
                state.ytPlayer.seekTo?.(t, true);
              }
              if (Number.isFinite(rate) && rate > 0) {
                state.ytPlayer.setPlaybackRate?.(rate);
              }
              if (shouldPlay) {
                state.ytPlayer.playVideo?.();
              }
            }
          },
          onStateChange: (e) => {
            state.ytPlaying = e.data === YT.PlayerState.PLAYING;
            if (e.data === YT.PlayerState.PLAYING) stopSlowBack();
            if (e.data === YT.PlayerState.PLAYING) setVideoUi(true, '動画: 再生中');
            if (e.data === YT.PlayerState.PAUSED) setVideoUi(true, '動画: 停止中');
            if (e.data === YT.PlayerState.ENDED) setVideoUi(true, '動画: 終了');
          }
        }
      });
      startMonitor();
    };

    if (window.YT && window.YT.Player) {
      boot();
      return;
    }

    window.onYouTubeIframeAPIReady = boot;
    const script = document.createElement('script');
    script.src = 'https://www.youtube.com/iframe_api';
    document.head.appendChild(script);

    ytPlayBtn?.addEventListener('click', () => {
      if (!state.ytPlayer || !state.ytReady) return;
      stopSlowBack();
      state.ytPlayer.setPlaybackRate?.(1);
      state.ytPlayer.playVideo?.();
    });

    ytPauseBtn?.addEventListener('click', () => {
      if (!state.ytPlayer || !state.ytReady) return;
      stopSlowBack();
      state.ytPlayer.pauseVideo?.();
    });

    ytRwBtn?.addEventListener('click', () => {
      if (!state.ytPlayer || !state.ytReady) return;
      stopSlowBack();
      const cur = Number(state.ytPlayer.getCurrentTime?.() || 0);
      state.ytPlayer.seekTo?.(Math.max(0, cur - 5), true);
    });

    ytFfBtn?.addEventListener('click', () => {
      if (!state.ytPlayer || !state.ytReady) return;
      stopSlowBack();
      const cur = Number(state.ytPlayer.getCurrentTime?.() || 0);
      const dur = Number(state.ytPlayer.getDuration?.() || 0);
      const next = dur > 0 ? Math.min(dur, cur + 5) : (cur + 5);
      state.ytPlayer.seekTo?.(next, true);
    });

    ytSlowBtn?.addEventListener('click', () => {
      if (!state.ytPlayer || !state.ytReady) return;
      stopSlowBack();
      state.ytPlayer.setPlaybackRate?.(0.5);
      state.ytPlayer.playVideo?.();
      setVideoUi(true, '動画: スロー再生(0.5x)');
    });

    ytSlowBackBtn?.addEventListener('click', () => {
      if (!state.ytPlayer || !state.ytReady) return;
      if (state.slowBackTimer) {
        stopSlowBack();
        setVideoUi(true, '動画: 停止中');
        return;
      }
      state.ytPlayer.pauseVideo?.();
      ytSlowBackBtn.textContent = '◼ 戻し停止';
      setVideoUi(true, '動画: スロー戻し(疑似)');
      state.slowBackTimer = window.setInterval(() => {
        const cur = Number(state.ytPlayer.getCurrentTime?.() || 0);
        const next = Math.max(0, cur - 0.25);
        state.ytPlayer.seekTo?.(next, true);
        if (next <= 0.01) {
          stopSlowBack();
          setVideoUi(true, '動画: 先頭');
        }
      }, 250);
    });
  };

  const setSelectedFact = (id) => {
    state.selectedFactId = id;
    document.querySelectorAll('.fact-node').forEach((el) => {
      const sid = Number(el.dataset.stepInstId || 0);
      el.classList.toggle('selected', sid === id);
    });
    updateExpandedRows();
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

    const applyLocalPreview = (el) => {
      if (!el.matches('select[data-type="step"][data-field="step_def_id"]')) return;
      const factNode = el.closest('.fact-node');
      if (!factNode) return;
      const label = el.options?.[el.selectedIndex]?.text?.trim();
      if (!label) return;
      const title = factNode.querySelector('.card-summary strong');
      if (title) title.textContent = label;
    };

    const applyReasonPreview = (el) => {
      if (!el.matches('textarea[data-type="opt"][data-field="reason"]')) return;
      const reasonCard = el.closest('.reason-card');
      if (!reasonCard) return;
      const preview = reasonCard.querySelector('.reason-preview');
      if (!preview) return;
      const text = (el.value || '').trim();
      preview.textContent = text === '' ? '理由未入力' : text;
    };

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
        applyLocalPreview(el);
        applyReasonPreview(el);
        clearTimeout(debounceMap.get(el));
        debounceMap.set(el, setTimeout(save, 350));
      };
      el.addEventListener('input', handler);
      el.addEventListener('change', handler);
    });
  };

  const refresh = () => {
    saveVideoState();
    location.href = new URL(location.href).toString();
  };

  const pickChoiceDefIdForStep = (stepInstId) => {
    const all = Array.isArray(window.__APP__.choiceDefIds) ? window.__APP__.choiceDefIds.map(Number).filter(Boolean) : [];
    const used = new Set(
      Array.from(document.querySelectorAll(`.if-node[data-if-step="${stepInstId}"]`))
        .map((node) => Number(node.dataset.choiceDefId || 0))
        .filter(Boolean)
    );
    const found = all.find((id) => !used.has(id));
    return Number(found || window.__APP__.defaultChoiceDefId || 0);
  };

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
            const choiceDefId = Number(document.getElementById(`add-choice-def-${stepInstId}`)?.value || pickChoiceDefIdForStep(stepInstId));
            await api('add_option', { submission_id: submissionId, step_inst_id: stepInstId, choice_def_id: choiceDefId });
            return refresh();
          }
          if (action === 'select-option') {
            await api('select_option', { submission_id: submissionId, alt_choice_id: Number(btn.dataset.altChoiceId) });
            return refresh();
          }
          if (action === 'unselect-option') {
            await api('unselect_option', { submission_id: submissionId, alt_choice_id: Number(btn.dataset.altChoiceId) });
            return refresh();
          }
          if (action === 'delete-option') {
            if (!confirm('この Option を削除しますか？')) return;
            await api('delete_option', { submission_id: submissionId, alt_choice_id: Number(btn.dataset.altChoiceId) });
            return refresh();
          }
          if (action === 'start-reason') {
            const card = btn.closest('.reason-card');
            card?.classList.add('editing');
            const textarea = card?.querySelector('textarea[data-field="reason"]');
            textarea?.focus();
            updateExpandedRows();
            return;
          }
          if (action === 'delete-reason') {
            if (!confirm('この判断理由を削除しますか？')) return;
            await api('update_option', { submission_id: submissionId, alt_choice_id: Number(btn.dataset.altChoiceId), fields: { reason: null } });
            return refresh();
          }
        } catch (err) {
          toast(err.message);
        }
      });
    });
  };

  const hookFactInteractions = () => {
    document.querySelectorAll('.fact-node').forEach((fact) => {
      fact.addEventListener('click', (e) => {
        if (e.target.closest('button,input,textarea,select,label')) return;
        toggleSelectedFact(Number(fact.dataset.stepInstId));

        if (!state.ytPlayer || !state.ytReady) return;
        const YT = window.YT;
        if (!YT) return;
        const currentState = Number(state.ytPlayer.getPlayerState?.() ?? -999);
        if (currentState !== YT.PlayerState.PAUSED) return;

        const now = Number(state.ytPlayer.getCurrentTime?.() || 0);
        const tsInput = fact.querySelector('input[data-field="timestamp_sec"]');
        if (!tsInput) return;
        tsInput.value = now.toFixed(1);
        tsInput.dispatchEvent(new Event('input', { bubbles: true }));
        tsInput.dispatchEvent(new Event('change', { bubbles: true }));
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
            const choiceDefId = pickChoiceDefIdForStep(stepInstId);
            await api('add_option', { submission_id: submissionId, step_inst_id: stepInstId, choice_def_id: choiceDefId });
            return refresh();
          }

          if (dir === 'right') {
            const choiceDefId = pickChoiceDefIdForStep(stepInstId);
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
    const scale = state.scale || 1;
    const toLocalX = (x) => (x - wr.left) / scale;
    const toLocalY = (y) => (y - wr.top) / scale;
    const markers = ['4da3ff', '86e5c0', 'ff6d6d']
      .map((c) => `<marker id="arr-${c}" markerWidth="12" markerHeight="12" refX="9" refY="4" orient="auto"><path d="M0,0 L0,8 L10,4 z" fill="#${c}"/></marker>`)
      .join('');
    let svg = `<defs>${markers}</defs>`;

    document.querySelectorAll('.reason-card[data-reason-target-step]').forEach((reason) => {
      const textarea = reason.querySelector('textarea[data-field="reason"]');
      const reasonText = (textarea?.value || '').trim();
      const reasonEmpty = reasonText === '';

      const currentStepId = Number((reason.id || '').replace('reason-', ''));
      const target = Number(reason.dataset.reasonTargetStep || 0);
      if (!target) return;

      const currentFact = document.getElementById(`step-${currentStepId}`);
      const fact = document.getElementById(`step-${target}`);
      if (!currentFact || !fact) return;

      const fromFact = currentFact.getBoundingClientRect();
      const reasonRect = reason.getBoundingClientRect();
      const toFact = fact.getBoundingClientRect();

      if (reasonEmpty) {
        svg += curvePath(
          toLocalX(fromFact.right),
          toLocalY(fromFact.top + fromFact.height / 2),
          toLocalX(toFact.left),
          toLocalY(toFact.top + toFact.height / 2),
          '#4da3ff',
          3.2,
          false
        );
      } else {
        svg += curvePath(
          toLocalX(fromFact.right),
          toLocalY(fromFact.top + fromFact.height / 2),
          toLocalX(reasonRect.left),
          toLocalY(reasonRect.top + reasonRect.height / 2),
          '#4da3ff',
          3.2,
          false
        );

        svg += curvePath(
          toLocalX(reasonRect.right),
          toLocalY(reasonRect.top + reasonRect.height / 2),
          toLocalX(toFact.left),
          toLocalY(toFact.top + toFact.height / 2),
          '#ff6d6d',
          3.2,
          false
        );
      }

      const nextIfNodes = Array.from(document.querySelectorAll(`.if-node[data-if-step="${target}"]`));
      if (!nextIfNodes.length) return;

      const selectedIf = nextIfNodes.find((node) => node.classList.contains('selected'));
      nextIfNodes.forEach((ifNode) => {
        if (!reasonEmpty && selectedIf && ifNode === selectedIf) return;
        const ifRect = ifNode.getBoundingClientRect();
        svg += curvePath(
          toLocalX(reasonEmpty ? fromFact.right : reasonRect.right),
          toLocalY(reasonEmpty ? (fromFact.top + fromFact.height / 2) : (reasonRect.top + reasonRect.height / 2)),
          toLocalX(ifRect.left),
          toLocalY(ifRect.top + ifRect.height / 2),
          '#86e5c0',
          2.8,
          true
        );
      });

      if (!reasonEmpty && selectedIf) {
        const selectedIfRect = selectedIf.getBoundingClientRect();
        svg += curvePath(
          toLocalX(reasonRect.right),
          toLocalY(reasonRect.top + reasonRect.height / 2 + 8),
          toLocalX(selectedIfRect.left),
          toLocalY(selectedIfRect.top + selectedIfRect.height / 2 + 8),
          '#ff6d6d',
          2.8,
          true
        );
      }
    });

    overlay.innerHTML = svg;
  };

  loadViewState();
  syncLaneLayout();
  hookCanvasPanZoom();
  hookAutoSave();
  hookButtons();
  hookFactInteractions();
  initInputMode();
  initYouTubeBackground();
  updateExpandedRows();
  syncLaneHeadPositions();
  recalcArrows();

  let raf = null;
  const schedule = () => {
    if (raf) return;
    raf = requestAnimationFrame(() => {
      raf = null;
      syncLaneLayout();
      updateExpandedRows();
      syncLaneHeadPositions();
      recalcArrows();
    });
  };
  window.addEventListener('resize', schedule);
  document.addEventListener('focusin', schedule);
  document.addEventListener('focusout', schedule);
  if (window.MutationObserver && turnStack) {
    new MutationObserver(schedule).observe(turnStack, { childList: true, subtree: true, attributes: true });
  }
})();
