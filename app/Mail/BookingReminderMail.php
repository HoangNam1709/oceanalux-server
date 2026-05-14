<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class BookingReminderMail extends Mailable
{
    use Queueable, SerializesModels;

    public $booking;

    public function __construct($booking)
    {
        $this->booking = $booking;
    }

    /**
     * SUBJECT MAIL
     */
    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Nhắc nhở: Chuyến đi của Quý khách sẽ khởi hành sau 48h - OceanaLux Cruises',
        );
    }

    /**
     * NỘI DUNG MAIL
     */
    public function content(): Content
    {
        return new Content(
            view: 'emails.booking_reminder',   
        );
    }
}