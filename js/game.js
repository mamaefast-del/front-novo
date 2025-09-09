// js/game.js
document.addEventListener('DOMContentLoaded', () => {
  const reelStrip = document.getElementById('reelStrip');
  const dataEl = document.getElementById('gameData');

  // UI/Modal
  const btnJogarNovamente = document.querySelector('.btn-jogar');
  const btnJogarNovamenteOculto = document.getElementById('btnJogarNovamente');
  const resultadoModal = document.getElementById('resultadoModal');
  const modalTitle = document.getElementById('modalTitle');
  const modalImage = document.getElementById('modalImage');
  const modalText = document.getElementById('modalText');
  const modalContinueBtn = document.getElementById('modalContinueBtn');

  // Portão e wrapper (para cálculo do tamanho)
  const reelWrapper = document.getElementById('reelWrapper');
  const gateOverlay = document.getElementById('gateOverlay');
  const gateLeftImg = document.getElementById('gateLeft');

  // Áudio opcional
  const spinAudio = new Audio('./images/audioroleta.mp3');

  if (!reelStrip || !dataEl || !reelWrapper) return;

  // ========================
  // Configurações de animação
  // ========================
  const LOOPS = 10;
  const DURATION_S = 5;
  const EASING = 'cubic-bezier(0.25, 0.1, 0.25, 1)';
  const GATE_OPEN_DELAY_MS = 150;
  const GATE_TRANSITION_MS = 1000;

  // ========================
  // Dados do PHP
  // ========================
  let segmentos;
  try {
    segmentos = JSON.parse(dataEl.dataset.segmentos || '[]');
  } catch (e) { segmentos = []; }
  const premioReal = Number(dataEl.dataset.premioReal || 0);
  const premioChave = dataEl.dataset.premioChave || null;
  const moeda = dataEl.dataset.moeda || 'R$';

  // ========================
  // Helpers
  // ========================
  function formatMoeda(valor, simbolo) {
    return `${simbolo} ${parseFloat(valor).toLocaleString('pt-BR', { minimumFractionDigits: 2, maximumFractionDigits: 2 })}`;
  }

  function showModalGanhou(valor) {
    stopAudio();
    const imagemPremio = dataEl.dataset.premioImagem || '';
    if (valor > 0 && imagemPremio) {
      modalImage.src = imagemPremio;
      modalImage.style.display = 'block';
    } else {
      modalImage.style.display = 'none';
    }
    if (valor > 0) {
      modalTitle.textContent = 'Parabéns!';
      modalText.innerHTML = 'Você ganhou <br> ' + formatMoeda(valor, moeda);
      if (typeof confetti === 'function') {
        confetti({ particleCount: 150, spread: 90, origin: { y: 0.6 } });
      }
    } else {
      modalTitle.textContent = 'Que pena!';
      modalText.textContent = 'Você não ganhou desta vez. Tente novamente!';
      modalImage.style.display = 'none';
    }
    if (resultadoModal) {
      resultadoModal.classList.add('show');
      setTimeout(() => (resultadoModal.style.display = 'flex'), 10);
    }
    if (btnJogarNovamenteOculto) btnJogarNovamenteOculto.style.display = 'inline-block';
  }

  function hideModal() {
    if (!resultadoModal) return;
    resultadoModal.classList.remove('show');
    setTimeout(() => {
      resultadoModal.style.display = 'none';
      closeGate();
      resetStripPosition();
    }, 300);
  }

  function playAudio() {
    try { spinAudio.currentTime = 0; spinAudio.play().catch(() => {}); } catch (e) {}
  }
  function stopAudio() {
    try { spinAudio.pause(); spinAudio.currentTime = 0; } catch (e) {}
  }

  // ===== Portão
  function openGate() { if (gateOverlay) gateOverlay.classList.add('open'); }
  function closeGate(){ if (gateOverlay) gateOverlay.classList.remove('open'); }

  // ========================
  // Tamanho da roleta = tamanho do portão
  // ========================
  function applyGateAspect() {
    if (!reelWrapper || !gateLeftImg) return;

    const applyVars = () => {
      // Aspecto = altura / largura
      const aspect = gateLeftImg.naturalHeight && gateLeftImg.naturalWidth
        ? (gateLeftImg.naturalHeight / gateLeftImg.naturalWidth)
        : 0.5; // fallback

      reelWrapper.style.setProperty('--gate-aspect', aspect.toString());

      // Após o browser calcular a altura pela aspect-ratio, pegamos a altura real
      requestAnimationFrame(() => {
        const h = reelWrapper.getBoundingClientRect().height || 240;
        reelWrapper.style.setProperty('--reel-h', `${Math.round(h)}px`);
      });
    };

    if (gateLeftImg.complete && gateLeftImg.naturalWidth) {
      applyVars();
    } else {
      gateLeftImg.addEventListener('load', applyVars, { once: true });
      gateLeftImg.addEventListener('error', applyVars, { once: true });
    }
  }

  function onResizeRecalc() {
    // Recalcula --reel-h quando a largura muda (mantendo proporção da imagem)
    const h = reelWrapper.getBoundingClientRect().height || 240;
    reelWrapper.style.setProperty('--reel-h', `${Math.round(h)}px`);
  }

  window.addEventListener('resize', () => {
    // Debounce simples
    clearTimeout(onResizeRecalc._t);
    onResizeRecalc._t = setTimeout(onResizeRecalc, 120);
  });

  // ========================
  // Construção da faixa longa
  // ========================
  const originalChildren = Array.from(reelStrip.children);
  if (originalChildren.length === 0) return;

  function buildLongStrip(done) {
    reelStrip.innerHTML = '';
    for (let i = 0; i < LOOPS; i++) {
      originalChildren.forEach((child) => {
        reelStrip.appendChild(child.cloneNode(true));
      });
    }
    if (typeof done === 'function') setTimeout(done, 50);
  }

  function resetStripPosition() {
    reelStrip.style.transition = 'none';
    reelStrip.style.transform = 'translateX(0)';
    // força reflow
    void reelStrip.offsetHeight;
  }

  // ========================
  // Animação até o alvo
  // ========================
  function spinToPrizeKey(prizeKey) {
    openGate();       // abre portões
    playAudio();      // som

    setTimeout(() => {
      const container = reelStrip.parentElement; // .reel-wrapper
      const containerWidth = container.clientWidth;

      const indexInSet = segmentos.indexOf(prizeKey);
      if (indexInSet === -1) {
        console.error('Prêmio não encontrado nos segmentos:', prizeKey, segmentos);
        stopAudio();
        return;
      }

      const longChildren = reelStrip.children;
      const itemsPerLoop = originalChildren.length;
      const lastIndex = longChildren.length - 1;

      const midLoop = Math.floor(LOOPS / 2);
      const targetIndex = Math.min(midLoop * itemsPerLoop + indexInSet, lastIndex);
      const targetEl = longChildren[targetIndex];

      const targetCenter = targetEl.offsetLeft + targetEl.offsetWidth / 2;
      const offset = targetCenter - containerWidth / 2;

      reelStrip.style.transition = `transform ${DURATION_S}s ${EASING}`;
      reelStrip.style.transform = `translateX(${-offset}px)`;

      const onEnd = () => {
        reelStrip.removeEventListener('transitionend', onEnd);
        showModalGanhou(premioReal);
      };
      reelStrip.addEventListener('transitionend', onEnd, { once: true });
    }, Math.max(GATE_OPEN_DELAY_MS, GATE_TRANSITION_MS * 0.6));
  }

  // ========================
  // Fluxo inicial
  // ========================
  applyGateAspect(); // calcula tamanho baseado no portão
  buildLongStrip(() => {
    if (premioChave) {
      if (btnJogarNovamente) btnJogarNovamente.style.display = 'none';
      spinToPrizeKey(premioChave);
    } else {
      closeGate();
      onResizeRecalc();
    }
  });

  // Modal
  if (modalContinueBtn) modalContinueBtn.addEventListener('click', hideModal);
});
