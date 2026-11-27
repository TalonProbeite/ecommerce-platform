package events

// Exchange names and types form the shared messaging contract.
const (
	ExchangeUser       = "user.events"
	ExchangeOrder      = "order.events"
	ExchangeCatalog    = "catalog.events"
	ExchangeSearch     = "search.events"
	ExchangeDLX        = "dlx.exchange"
	ExchangeKindTopic  = "topic"
	ExchangeKindFanout = "fanout"
)
