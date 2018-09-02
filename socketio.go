package main
import (
	"log"
	"net/http"
	"github.com/googollee/go-socket.io"
)
func main() {
	server, err := socketio.NewServer(nil)
	if err != nil {
		log.Fatal(err)
	}
	server.On("connection", func(so socketio.Socket) {
		log.Println("on connection")
		so.Join("chat")
		so.Emit("chat message", "welcome")
		so.On("chat message", func(msg string) {
			log.Println("msg:", msg)
			log.Println("emit", so.Emit("chat message", msg))
			so.BroadcastTo("chat", "chat message", msg)
		})
		so.On("disconnection", func() {
			log.Println("on disconnected	")
		})
	})
	server.On("error", func(so socketio.Socket, err error) {
		log.Println("error:", err)
	})
	http.Handle("/socket.io/", server)
	log.Println("serving at localhost:9501...")
	log.Fatal(http.ListenAndServe(":9501", nil))
}
