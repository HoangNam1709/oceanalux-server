<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Mail\Mailables\Attachment;
use Illuminate\Queue\SerializesModels;
use Barryvdh\DomPDF\Facade\Pdf;

class BookingSuccessMail extends Mailable
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
            subject: 'Xác nhận đặt chỗ thành công - OceanaLux Cruises (' . $this->booking->booking_code . ')',
        );
    }

    /**
     * NỘI DUNG MAIL (HTML + TEXT FALLBACK)
     */
    public function content(): Content
    {
        return new Content(
            view: 'emails.booking_success',   
        );
    }

    /**
     * ĐÍNH KÈM FILE PDF
     */
    public function attachments(): array
    {
        $pdf = Pdf::loadView('pdf.ticket', [
            'booking' => $this->booking
        ]);

        return [
            Attachment::fromData(
                fn () => $pdf->output(),
                'Ve-OceanaLux-' . $this->booking->booking_code . '.pdf'
            )->withMime('application/pdf'),
        ];
    }
}