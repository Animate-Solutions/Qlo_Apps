<?php
/** Reputation: the review dashboard, the needs-a-reply queue, manual entry and the import of portal exports. */
class AdminPulseCrmReviewsController extends ModuleAdminController
{
    public function __construct() { $this->bootstrap = true; parent::__construct(); $this->meta_title = $this->l('Reviews'); }

    public function initContent()
    {
        parent::initContent();
        $self = self::$currentIndex.'&token='.$this->token;
        $from = Tools::getValue('from', date('Y-m-d', strtotime('-12 month'))); $to = Tools::getValue('to', date('Y-m-d'));
        $this->context->smarty->assign(array(
            'reviews' => PulseCrmReview::all($from, $to, Tools::getValue('source') ?: null, (bool) Tools::getValue('unanswered'), 200),
            'needs_reply' => PulseCrmReview::needsReply(50), 'by_source' => PulseCrmReview::bySource($from, $to),
            'median_hours' => PulseCrmReview::medianResponseHours($from, $to), 'trend' => PulseCrmReview::trend(12),
            'sources' => array_keys(PulseCrmReview::$sources), 'from' => $from, 'to' => $to, 'source' => Tools::getValue('source'),
            'unanswered' => (int) Tools::getValue('unanswered'), 'r' => Tools::getValue('id_review') ? PulseCrmReview::get((int) Tools::getValue('id_review')) : null,
            'self_url' => $self, 'import_result' => $this->context->smarty->getTemplateVars('import_result'),
        ));
        $this->setTemplate('reviews.tpl');
    }

    public function postProcess()
    {
        try {
            if (Tools::isSubmit('saveReview')) {
                $r = PulseCrmReview::save(array('source' => Tools::getValue('source_new'), 'external_id' => Tools::getValue('external_id'), 'url' => Tools::getValue('url'),
                    'rating' => Tools::getValue('rating'), 'rating_scale' => Tools::getValue('rating_scale'), 'title' => Tools::getValue('title'), 'body' => Tools::getValue('body'),
                    'language' => Tools::getValue('language', 'en'), 'author' => Tools::getValue('author'), 'review_date' => Tools::getValue('review_date'),
                    'trip_type' => Tools::getValue('trip_type'), 'department' => Tools::getValue('department')), (int) Tools::getValue('id_review_a'));
                $this->confirmations[] = $r['created'] ? $this->l('Review added') : $this->l('Review updated');
            }
            if (Tools::isSubmit('respondReview')) { PulseCrmReview::respond((int) Tools::getValue('id_review_a'), Tools::getValue('response_text')); $this->confirmations[] = $this->l('Reply recorded — now post it on the portal itself'); }
            if (Tools::isSubmit('deleteReview')) { PulseCrmReview::remove((int) Tools::getValue('id_review_a')); $this->confirmations[] = $this->l('Review deleted'); }
            if (Tools::isSubmit('importReviews')) {
                $content = Tools::getValue('import_text');
                if (isset($_FILES['import_file']) && $_FILES['import_file']['error'] === UPLOAD_ERR_OK && is_uploaded_file($_FILES['import_file']['tmp_name'])) {
                    if ((int) $_FILES['import_file']['size'] > 4194304) { throw new PrestaShopException($this->l('That file is over 4 MB — split it')); }
                    $content = Tools::file_get_contents($_FILES['import_file']['tmp_name']);
                }
                $r = PulseCrmReview::import($content, Tools::getValue('import_source', 'other'), Tools::getValue('import_format', 'auto'));
                $this->context->smarty->assign('import_result', $r);
                $this->confirmations[] = sprintf($this->l('%1$d new, %2$d updated, %3$d skipped'), $r['created'], $r['updated'], $r['skipped']);
                foreach ($r['errors'] as $e) { $this->warnings[] = $e; }
            }
        } catch (Exception $e) { $this->errors[] = $e->getMessage(); }
        return parent::postProcess();
    }
}
