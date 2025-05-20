<?php

namespace Crm\UsersModule\Events;

use League\Event\AbstractEvent;
use League\Event\Emitter;
use Nette\Database\Table\ActiveRow;

/**
 * NotificationEvent serves for sending notification (e.g. emails, push notifications) to users.
 *
 * UserModule itself doesn't handle actual sending, other implementations (for example integrating with REMP Mailer)
 * listening for this event are required to be used.
 */
class NotificationEvent extends AbstractEvent
{
    private $user;

    /**
     * Template code should reference template - actual content - of notification. Implementation should be able to
     * find the content of notification based on the provided code.
     */
    private $templateCode;

    /**
     * Parameters (variables) for provided template. The presumption is that templates use kind of templating language
     * with possibility of injecting dynamic values through variables.
     */
    private $params;

    /**
     * Context serves for identification of why user received the notification. Implementations of event handlers
     * should ensure that user   One user Implementations of handlers
     */
    private $context;

    /**
     * Some notifications support attachments. You should pass them in following format:
     *  [
     *     [
     *       // Name of attachment. If no content is provided and `file` contains valid path, handlers should try
     *       // to load attachment content from the provided path.
     *       'file' => '/tmp/attachment.pdf',
     *
     *       // Raw content of attachment.
     *       'content' => 'raw_attachment_content',
     *     ]
     *  ]
     */
    private $attachments;

    /**
     * If the notification should not be sent immediately, you can schedule it for late processing.
     */
    private $scheduleAt;

    /**
     * Locale to use in notification.
     */
    private ?string $locale;

    public function __construct(
        Emitter $emitter,
        ?ActiveRow $user,
        string $templateCode,
        array $params = [],
        string $context = null,
        array $attachments = [],
        \DateTime $scheduleAt = null,
        NotificationContext $notificationContext = null,
        ?string $locale = null,
    ) {
        $this->user         = $user;
        $this->templateCode = $templateCode;
        $this->params       = $params;
        $this->context      = $context;
        $this->attachments  = $attachments;
        $this->scheduleAt   = $scheduleAt;
        $this->locale       = $locale;
        // Let modules modify NotificationEvent parameters
        $emitter->emit(new PreNotificationEvent($this, $notificationContext));
    }

    public function getUser(): ?ActiveRow
    {
        return $this->user;
    }

    public function getTemplateCode(): string
    {
        return $this->templateCode;
    }

    public function getParams(): array
    {
        return $this->params;
    }

    public function getContext(): ?string
    {
        return $this->context;
    }

    public function getAttachments(): array
    {
        return $this->attachments;
    }

    public function getScheduleAt(): ?\DateTime
    {
        return $this->scheduleAt;
    }

    public function getLocale(): ?string
    {
        return $this->locale;
    }

    public function setUser(?ActiveRow $user): void
    {
        $this->user = $user;
    }

    public function setTemplateCode(string $templateCode): void
    {
        $this->templateCode = $templateCode;
    }

    public function setParams(array $params): void
    {
        $this->params = $params;
    }

    public function setContext(string $context): void
    {
        $this->context = $context;
    }

    public function setAttachments(array $attachments): void
    {
        $this->attachments = $attachments;
    }

    public function setScheduleAt(\DateTime $scheduleAt): void
    {
        $this->scheduleAt = $scheduleAt;
    }

    public function setLocale(string $locale): void
    {
        $this->locale = $locale;
    }
}
