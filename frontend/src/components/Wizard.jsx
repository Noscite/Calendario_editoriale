import { useId } from 'react';
import { Check, ArrowLeft, ArrowRight, Loader2 } from 'lucide-react';

/**
 * <Wizard /> — componente generico multi-step.
 * Custom, zero-deps, coerente con il pattern visivo di ProjectWizard.jsx.
 *
 * Pattern d'uso (controlled): il parent gestisce currentStepId, formData,
 * validazione e PATCH al backend. Il Wizard rende solo progress + chrome.
 *
 * Props:
 *   - steps             (Array)  [{id, title, icon: LucideIcon}]
 *   - currentStepId     (number) step attivo
 *   - onStepChange      (fn)     (newId) => void  (per click su step già completati)
 *   - onNext            (fn)     () => void       (click "Avanti" — parent valida + PATCH)
 *   - onBack            (fn)     () => void       (click "Indietro")
 *   - onSubmit          (fn)     () => void       (click sull'ultimo step)
 *   - onSkip            (fn|null)                 (se step skippable)
 *   - header            (ReactNode) opzionale (es. <BrandCompletenessIndicator />)
 *   - children          (ReactNode) rendering dello step corrente (gestito dal parent via switch)
 *   - isSubmitting      (bool)   spinner sui bottoni
 *   - canGoNext         (bool)   abilita "Avanti"
 *   - nextLabel         (string) default "Avanti"
 *   - errorMessage      (string|null) banner errore sotto i bottoni
 *
 * ARIA: tablist su progress, role="tab" su ogni cerchio (cliccabili solo se
 * step.id < currentStepId), aria-current="step" sullo step attivo, role="tabpanel"
 * sull'area contenuto.
 */
export default function Wizard({
  steps,
  currentStepId,
  onStepChange,
  onNext,
  onBack,
  onSubmit,
  onSkip = null,
  header = null,
  children,
  isSubmitting = false,
  canGoNext = true,
  nextLabel = 'Avanti',
  errorMessage = null,
}) {
  const tabsId = useId();
  const isFirst = currentStepId === steps[0]?.id;
  const isLast  = currentStepId === steps[steps.length - 1]?.id;

  const handleStepClick = (stepId) => {
    // Cliccabile solo se step già completato (id minore del corrente)
    if (stepId < currentStepId && !isSubmitting) {
      onStepChange?.(stepId);
    }
  };

  return (
    <div className="min-h-screen bg-gray-50">
      {/* Sticky chrome: progress + header opzionale */}
      <div className="bg-white border-b sticky top-0 z-30">
        <div className="max-w-4xl mx-auto px-4 py-6">
          <div role="tablist" aria-label="Step del wizard" className="flex justify-between">
            {steps.map((step, idx) => {
              const Icon = step.icon;
              const isCurrent   = currentStepId === step.id;
              const isCompleted = currentStepId > step.id;
              const isClickable = step.id < currentStepId;

              return (
                <div key={step.id} className="flex items-center">
                  <button
                    type="button"
                    role="tab"
                    id={`${tabsId}-tab-${step.id}`}
                    aria-controls={`${tabsId}-panel`}
                    aria-current={isCurrent ? 'step' : undefined}
                    aria-disabled={!isClickable && !isCurrent}
                    tabIndex={isClickable || isCurrent ? 0 : -1}
                    disabled={!isClickable}
                    onClick={() => handleStepClick(step.id)}
                    className={[
                      'flex items-center justify-center w-10 h-10 rounded-full transition-all',
                      isCompleted
                        ? 'bg-[#3DAFA8] text-white hover:opacity-90 cursor-pointer'
                        : isCurrent
                          ? 'bg-[#3DAFA8] ring-4 ring-teal-200 text-white'
                          : 'bg-gray-200 text-gray-500 cursor-not-allowed',
                    ].join(' ')}
                  >
                    {isCompleted ? <Check size={20} /> : <Icon size={20} />}
                  </button>

                  <span className={[
                    'ml-2 text-sm hidden sm:block',
                    currentStepId >= step.id ? 'text-[#3DAFA8] font-medium' : 'text-gray-500',
                  ].join(' ')}>
                    {step.title}
                  </span>

                  {idx < steps.length - 1 && (
                    <div className={[
                      'w-8 sm:w-16 h-1 mx-2 transition-colors',
                      currentStepId > step.id ? 'bg-[#3DAFA8]' : 'bg-gray-200',
                    ].join(' ')} />
                  )}
                </div>
              );
            })}
          </div>

          {header && <div className="mt-6">{header}</div>}
        </div>
      </div>

      {/* Contenuto step */}
      <main className="max-w-4xl mx-auto px-4 py-8">
        <div
          id={`${tabsId}-panel`}
          role="tabpanel"
          aria-labelledby={`${tabsId}-tab-${currentStepId}`}
          className="bg-white rounded-xl shadow-sm p-8"
        >
          {children}

          {errorMessage && (
            <div className="mt-6 bg-red-50 border-l-4 border-red-400 text-red-700 px-4 py-3 rounded">
              {errorMessage}
            </div>
          )}
        </div>

        {/* Footer azioni */}
        <div className="mt-6 flex items-center justify-between gap-3">
          <button
            type="button"
            onClick={onBack}
            disabled={isFirst || isSubmitting}
            className={[
              'flex items-center gap-2 px-4 py-2 rounded-lg border transition-colors',
              isFirst || isSubmitting
                ? 'border-gray-200 text-gray-300 cursor-not-allowed'
                : 'border-gray-300 text-gray-700 hover:bg-gray-50',
            ].join(' ')}
          >
            <ArrowLeft size={18} /> Indietro
          </button>

          <div className="flex items-center gap-3">
            {onSkip && !isLast && (
              <button
                type="button"
                onClick={onSkip}
                disabled={isSubmitting}
                className="px-4 py-2 text-gray-600 hover:text-[#3DAFA8] disabled:opacity-50 transition-colors"
              >
                Salta per ora
              </button>
            )}

            <button
              type="button"
              onClick={isLast ? onSubmit : onNext}
              disabled={!canGoNext || isSubmitting}
              className={[
                'flex items-center gap-2 px-6 py-2 rounded-lg font-medium transition-colors',
                canGoNext && !isSubmitting
                  ? 'bg-[#3DAFA8] text-white hover:bg-[#2C3E50]'
                  : 'bg-gray-200 text-gray-400 cursor-not-allowed',
              ].join(' ')}
            >
              {isSubmitting && <Loader2 size={18} className="animate-spin" />}
              {!isSubmitting && (isLast ? 'Salva e completa' : nextLabel)}
              {!isSubmitting && !isLast && <ArrowRight size={18} />}
            </button>
          </div>
        </div>
      </main>
    </div>
  );
}
