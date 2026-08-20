{{--
    The running footer, drawn by Chrome on EVERY page of the export.

    Styled inline and self-contained: Chrome renders header and footer templates as their own
    documents, so the report's own <style> block does not reach this markup.
--}}
<div style="width:100%;font-family:Arial,sans-serif;font-size:8px;color:#6b7280;padding:0 12px;display:flex;justify-content:space-between;align-items:center;">
    <span>This is an electronically generated report. No signature is required.</span>
    <span>Page <span class="pageNumber"></span> of <span class="totalPages"></span></span>
</div>
