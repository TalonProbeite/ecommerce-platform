package dto

type GetNotificationByIDRequest struct {
	ID string `param:"id" validate:"required"`
}

type GetNotificationsHistoryRequest struct {
	Status    string `query:"status"`
	Channel   string `query:"channel"`
	Recipient string `query:"recipient"`

	StartDate string `query:"start_date"`
	EndDate   string `query:"end_date"`

	Limit  int64 `query:"limit" validate:"gte=0,lte=100"`
	Offset int64 `query:"offset" validate:"gte=0"`
}
