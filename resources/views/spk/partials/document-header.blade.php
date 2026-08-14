<div class="spkDocumentHeader" aria-label="Document header">
    <div class="spkDocumentHeaderLogo">
        <img
            src="{{ $header['logoUrl'] }}"
            alt="Logo"
            class="spkDocumentHeaderLogoImg"
        >
    </div>

    <div class="spkDocumentHeaderCenter">
        <div class="spkDocumentHeaderFormTitle">
            {{ strtoupper($header['formTitle']) }}
        </div>
        <div class="spkDocumentHeaderCompany">
            {{ $header['companyName'] }}
        </div>
    </div>

    <div class="spkDocumentHeaderMeta">
        <div>Doc No. : {{ $header['docNo'] }}</div>
        <div>Issue No. : {{ $header['issueNo'] }}</div>
        <div>Revision: {{ $header['revision'] }}</div>
        <div>Date: {{ $header['issueDate'] }}</div>
    </div>
</div>
