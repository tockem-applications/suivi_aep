<?php
/**
 * Sélecteur de période (identique au tableau de bord AEP).
 *
 * @param array $periode Données retournées par RedevanceSynopsisHelper::compute()
 * @param array $options page, form_id, select_id, mf_debut_id, mf_fin_id, hidden, wrapper_class
 */
function render_periode_selector_form(array $periode, array $options = array())
{
    if (empty($periode['liste_mois_fact'])) {
        return;
    }

    $page = isset($options['page']) ? $options['page'] : '';
    $formId = isset($options['form_id']) ? $options['form_id'] : 'form_periode';
    $selectId = isset($options['select_id']) ? $options['select_id'] : 'periode_annee';
    $mfDebutId = isset($options['mf_debut_id']) ? $options['mf_debut_id'] : 'periode_mf_debut';
    $mfFinId = isset($options['mf_fin_id']) ? $options['mf_fin_id'] : 'periode_mf_fin';
    $hidden = isset($options['hidden']) && is_array($options['hidden']) ? $options['hidden'] : array();
    $wrapperClass = isset($options['wrapper_class']) ? $options['wrapper_class'] : 'd-flex flex-wrap align-items-end gap-2';

    $spm = $periode['periode_mode'];
    $snm = (int) $periode['nb_mois_glissant'];
    $periodeLibelle = isset($periode['periode_libelle']) ? $periode['periode_libelle'] : '';
    $isIntervalle = ($spm === 'intervalle');
    ?>
    <form method="get" action="" id="<?php echo htmlspecialchars($formId, ENT_QUOTES, 'UTF-8'); ?>"
        class="<?php echo htmlspecialchars($wrapperClass, ENT_QUOTES, 'UTF-8'); ?>">
        <?php if ($page !== ''): ?>
            <input type="hidden" name="page" value="<?php echo htmlspecialchars($page, ENT_QUOTES, 'UTF-8'); ?>">
        <?php endif; ?>
        <?php foreach ($hidden as $hName => $hVal): ?>
            <input type="hidden" name="<?php echo htmlspecialchars($hName, ENT_QUOTES, 'UTF-8'); ?>"
                value="<?php echo htmlspecialchars((string) $hVal, ENT_QUOTES, 'UTF-8'); ?>">
        <?php endforeach; ?>
        <div>
            <label for="<?php echo htmlspecialchars($selectId, ENT_QUOTES, 'UTF-8'); ?>" class="form-label small text-muted mb-0">Période</label>
            <select name="annee" id="<?php echo htmlspecialchars($selectId, ENT_QUOTES, 'UTF-8'); ?>"
                class="form-select form-select-sm" style="min-width: 8rem; max-width: 12rem;"
                title="<?php echo $periodeLibelle !== '' ? htmlspecialchars($periodeLibelle, ENT_QUOTES, 'UTF-8') : ''; ?>"
                onchange="this.form.submit();">
                <option value="m3" <?php echo ($spm === '12' && $snm === 4) ? 'selected' : ''; ?>>3 derniers mois</option>
                <option value="m6" <?php echo ($spm === '12' && $snm === 7) ? 'selected' : ''; ?>>6 derniers mois</option>
                <option value="" <?php echo ($spm === '12' && in_array($snm, array(12, 13), true)) ? 'selected' : ''; ?>>12 derniers mois</option>
                <option value="tous" <?php echo $spm === 'tous' ? 'selected' : ''; ?>>Tous les mois</option>
                <option value="intervalle" <?php echo $spm === 'intervalle' ? 'selected' : ''; ?>>Intervalle (mois)</option>
                <?php foreach ($periode['annees_disponibles'] as $yDisp): ?>
                    <option value="<?php echo (int) $yDisp; ?>" <?php echo $spm === 'annee' && (int) $periode['annee_vue'] === (int) $yDisp ? 'selected' : ''; ?>>
                        <?php echo (int) $yDisp; ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="d-flex flex-wrap align-items-end gap-1 <?php echo $isIntervalle ? '' : 'opacity-50'; ?>">
            <div>
                <label for="<?php echo htmlspecialchars($mfDebutId, ENT_QUOTES, 'UTF-8'); ?>" class="form-label small text-muted mb-0">Mois début</label>
                <select name="mf_debut" id="<?php echo htmlspecialchars($mfDebutId, ENT_QUOTES, 'UTF-8'); ?>"
                    class="form-select form-select-sm" style="min-width: 9rem; max-width: 11rem;"
                    <?php echo $isIntervalle ? '' : 'disabled'; ?>
                    onchange="this.form.submit();">
                    <?php foreach ($periode['liste_mois_fact'] as $lm): ?>
                        <option value="<?php echo (int) $lm['id']; ?>" <?php echo (int) $lm['id'] === (int) $periode['mf_debut_id'] ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars(getLetterMonth($lm['mois'])); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div>
                <label for="<?php echo htmlspecialchars($mfFinId, ENT_QUOTES, 'UTF-8'); ?>" class="form-label small text-muted mb-0">Mois fin</label>
                <select name="mf_fin" id="<?php echo htmlspecialchars($mfFinId, ENT_QUOTES, 'UTF-8'); ?>"
                    class="form-select form-select-sm" style="min-width: 9rem; max-width: 11rem;"
                    <?php echo $isIntervalle ? '' : 'disabled'; ?>
                    onchange="this.form.submit();">
                    <?php foreach ($periode['liste_mois_fact'] as $lm): ?>
                        <option value="<?php echo (int) $lm['id']; ?>" <?php echo (int) $lm['id'] === (int) $periode['mf_fin_id'] ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars(getLetterMonth($lm['mois'])); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
        </div>
    </form>
    <?php
}
