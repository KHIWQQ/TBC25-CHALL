import glob
import os
import shlex
import socketserver
import uuid

import pexpect

# Configuration
PORT = 5050
HOST = "0.0.0.0"
OUTPUT_DIR = "/tmp/outputs"

# Ensure output directory exists
os.makedirs(OUTPUT_DIR, exist_ok=True)


class EchoRequestHandler(socketserver.BaseRequestHandler):
    """
    The request handler class for our server.
    It is instantiated once per connection to the server.
    """

    def handle(self):
        """Handle the client connection."""
        try:
            # Send welcome message and menu
            menu = """
Welcome to Device Echo!
----------------------
Select an option:
1. Execute echo command
2. Read saved output
3. Exit
            """
            self.request.send(menu.encode())

            while True:
                self.request.send(b"\nEnter your choice (1-3): ")
                choice = self.request.recv(1024).decode().strip()

                if choice == "1":
                    # Execute echo command
                    self.request.send(b"Enter text to echo: ")
                    text = (
                        self.request.recv(1024)
                        .decode()
                        .strip()
                        .replace("\n", "")
                        .replace(" ", "")
                    )

                    # Quote the input for safety
                    quoted_text = shlex.quote(text)

                    # Generate a unique filename with UUID
                    filename = str(uuid.uuid4()) + ".txt"
                    filepath = os.path.join(OUTPUT_DIR, filename)

                    # Execute the echo command with pexpect and tee
                    cmd = f"echo {quoted_text} 2>&1 > {filepath}\n"
                    child = pexpect.spawn("/bin/bash")
                    child.sendline(cmd)
                    child.sendline("exit")
                    child.sendeof()
                    child.close()

                    response = f"Command executed. Saved to file: {filename}\n"
                    self.request.send(response.encode())

                elif choice == "2":
                    # Read saved output
                    self.request.send(
                        b"Enter filename pattern (no wildcard support! single file path only!): "
                    )
                    filename = self.request.recv(1024).decode().strip()

                    # Build the file path
                    full_filepath = os.path.join(OUTPUT_DIR, filename)

                    # Check if there is a file with this path
                    matching_file = glob.glob(full_filepath)

                    if not matching_file:
                        self.request.send(b"File was not found!\n")
                    elif len(matching_file) > 1:
                        self.request.send(
                            b"Multiple files NOT allowed! No wildcards are allowed!\n"
                        )
                    else:
                        # Read the file content and send it to the client
                        with open(matching_file[0], "r") as f:
                            content = f.read()
                            self.request.send(
                                b"File content: " + content.encode() + b"\n"
                            )
                elif choice == "3":
                    # Exit
                    self.request.send(b"Goodbye!\n")
                    break
                else:
                    self.request.send(b"Invalid choice. Please try again.\n")

        except Exception:
            # Ignore annoying connection errors
            pass


def start_server():
    """Start the TCP server using ThreadingTCPServer."""
    # Create the server
    socketserver.ThreadingTCPServer.allow_reuse_address = True
    socketserver.ThreadingTCPServer.daemon_threads = True
    server = socketserver.ThreadingTCPServer((HOST, PORT), EchoRequestHandler)
    try:
        print(f"[*] Listening on {HOST}:{PORT}")
        server.serve_forever()
    except KeyboardInterrupt:
        print("\n[*] Shutting down server...")
    except Exception as e:
        print(f"Server error: {str(e)}")
    finally:
        server.server_close()


if __name__ == "__main__":
    start_server()
