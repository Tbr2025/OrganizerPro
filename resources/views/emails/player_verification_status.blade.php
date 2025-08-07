<h2>Hi {{ $player->name }},</h2>

<p>Here is the summary of your profile verification status:</p>

<h3>✅ Verified Fields:</h3>
<ul>
    @foreach ($verifiedFields as $field)
        <li>{{ ucfirst(str_replace('_', ' ', $field)) }}</li>
    @endforeach
</ul>

<h3>❌ Not Verified Fields:</h3>
<ul>
    @foreach ($unverifiedFields as $field)
        <li>{{ ucfirst(str_replace('_', ' ', $field)) }}</li>
    @endforeach
</ul>

<p>If you believe any information is incorrect, please contact the tournament administrator.</p>

<p>Thanks,<br>The Admin Team</p>
<p><small>This is an automated message, please do not reply.</small></p>