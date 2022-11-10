<?php

namespace App\Notifications;

use App\EmailNotificationSetting;
use App\Expense;
use App\ExpenseRecurring;
use App\PushNotificationSetting;
use App\SlackSetting;

use Illuminate\Notifications\Messages\SlackMessage;
use Illuminate\Notifications\Messages\MailMessage;
use NotificationChannels\OneSignal\OneSignalChannel;
use NotificationChannels\OneSignal\OneSignalMessage;

class NewExpenseRecurringMember extends BaseNotification
{


    /**
     * Create a new notification instance.
     *
     * @return void
     */
    private $expense;
    public function __construct(ExpenseRecurring $expense)
    {
        parent::__construct();
        $this->expense = $expense;
        $this->emailSetting = EmailNotificationSetting::where('setting_name', 'New Expense/Added by Member')->first();
        $this->pushNotification = PushNotificationSetting::first();
    }

    /**
     * Get the notification's delivery channels.
     *
     * @param  mixed  $notifiable
     * @return array
     */
    public function via($notifiable)
    {
        $via = ['database'];

        if ($this->emailSetting->send_email == 'yes' && $notifiable->email_notifications) {
            array_push($via, 'mail');
        }

        if ($this->emailSetting->send_slack == 'yes') {
            array_push($via, 'slack');
        }

        if ($this->emailSetting->send_push == 'yes' && $this->pushNotification->status == 'active') {
            array_push($via, OneSignalChannel::class);
        }

        return $via;
    }

    /**
     * Get the mail representation of the notification.
     *
     * @param  mixed  $notifiable
     * @return \Illuminate\Notifications\Messages\MailMessage
     */
    public function toMail($notifiable)
    {
        $user = $notifiable;
        $url = url('/');

        return (new MailMessage)
            ->subject(__('email.newExpenseRecurring.subject') . ' - ' . config('app.name'))
            ->greeting(__('email.hello') . ' ' . ucwords($user->name) . '!')
            ->line(__('email.newExpenseRecurring.subject') . '.')
            ->line(__('app.employee') . ': ' . ucwords($this->expense->user->name))
            ->line(__('modules.expenses.itemName') . ': ' . $this->expense->item_name)
            ->line(__('app.price') . ': ' . $this->expense->currency->currency_symbol . $this->expense->price)
            ->action(__('email.loginDashboard'), $url)
            ->line(__('email.thankyouNote'));
    }

    /**
     * Get the array representation of the notification.
     *
     * @param  mixed  $notifiable
     * @return array
     */
    public function toArray($notifiable)
    {
        return $this->expense->toArray();
    }

    /**
     * Get the Slack representation of the notification.
     *
     * @param  mixed  $notifiable
     * @return SlackMessage
     */
    public function toSlack($notifiable)
    {
        $slack = SlackSetting::first();
        if (count($notifiable->employee) > 0 && (!is_null($notifiable->employee[0]->slack_username) && ($notifiable->employee[0]->slack_username != ''))) {
            return (new SlackMessage())
                ->from(config('app.name'))
                ->image($slack->slack_logo_url)
                ->to('@' . $notifiable->employee[0]->slack_username)
                ->content(__('email.newExpenseRecurring.subject') . ' - ' . $this->expense->item_name . " - " . currency_position($this->expense->price, $this->expense->currency->currency_symbol) . "\n" . url('/login'));
        }
        return (new SlackMessage())
            ->from(config('app.name'))
            ->image($slack->slack_logo_url)
            ->content('This is a redirected notification. Add slack username for *' . ucwords($notifiable->name) . '*');
    }

    public function toOneSignal($notifiable)
    {
        return OneSignalMessage::create()
            ->subject(__('email.newExpenseRecurring.subject'))
            ->body($this->expense->item_name . ' by ' . ucwords($this->expense->user->name));
    }
}
