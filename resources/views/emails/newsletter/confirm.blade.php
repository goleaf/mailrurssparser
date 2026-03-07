<p>Здравствуйте{{ $subscriber->name ? ', '.$subscriber->name : '' }}.</p>
<p>Подтвердите подписку на новости:</p>
<p>
    <a href="{{ url('/api/v1/newsletter/confirm/'.$subscriber->token) }}">
        Подтвердить подписку
    </a>
</p>
<p>
    Отписаться:
    <a href="{{ url('/api/v1/newsletter/unsubscribe/'.$subscriber->token) }}">
        {{ url('/api/v1/newsletter/unsubscribe/'.$subscriber->token) }}
    </a>
</p>
