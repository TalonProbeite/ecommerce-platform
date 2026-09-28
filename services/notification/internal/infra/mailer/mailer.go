package mailer

import (
	"shop/notification/internal/config"
	"strconv"

	"gopkg.in/gomail.v2"
)

type Sender struct {
	dialer *gomail.Dialer
	from   string
}

func NewSender(cfg config.SMTPConfig, from string) (*Sender, error) {
	port, err := strconv.Atoi(cfg.SMTPPort)
	if err != nil {
		return nil, err
	}

	dialer := gomail.NewDialer(cfg.SMTPHost, port, cfg.SMTPUser, cfg.SMTPPass)

	conn, err := dialer.Dial()
	if err != nil {
		return nil, err
	}

	if err := conn.Close(); err != nil {
		return nil, err
	}

	return &Sender{
		dialer: dialer,
		from:   from,
	}, nil
}

func (s *Sender) SendHTML(to, subject, htmlBody string) error {
	m := gomail.NewMessage()

	m.SetHeader("From", s.from)
	m.SetHeader("To", to)
	m.SetHeader("Subject", subject)
	m.SetBody("text/html", htmlBody)

	return s.dialer.DialAndSend(m)
}
