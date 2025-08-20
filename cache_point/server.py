import asyncio
import logging

# Configuration
REDIS_HOST = "redis"
REDIS_PORT = 6379
SERVER_HOST = "0.0.0.0"
SERVER_PORT = 6000
MAX_MESSAGE_LENGTH = 512

# Fancy ASCII welcome message
WELCOME_MESSAGE = r"""                                                  
          Cache Point - v1.0
----------------------------------------
Feel free to store contents up to 512 bytes!
Only GET, SET and DEL commands are allowed!
Example: SET key value or GET key or DEL key
Type EXIT to close the connection
----------------------------------------

> """

# Setup logging
logging.basicConfig(
    level=logging.INFO, format="%(asctime)s - %(levelname)s - %(message)s"
)
logger = logging.getLogger("caas")


async def handle_redis_command(command: str) -> str:
    """
    Send a command to Redis and return the response.

    Args:
        command (str): The Redis command to send

    Returns:
        str: The response from Redis
    """
    try:
        reader, writer = await asyncio.open_connection(REDIS_HOST, REDIS_PORT)
        writer.write(command.encode() + b"\n")
        await writer.drain()
        data = await reader.read(4096)
        writer.close()
        await writer.wait_closed()
        return data.decode()
    except Exception as e:
        logger.error(f"Redis connection error: {e}")
        return f"Error connecting to Redis: {e}"


async def handle_client(
    reader: asyncio.StreamReader, writer: asyncio.StreamWriter
) -> None:
    """
    Handle a client connection.

    Args:
        reader: Stream reader for client connection
        writer: Stream writer for client connection
    """
    addr = writer.get_extra_info("peername")
    logger.info(f"New connection from {addr}")

    # Send welcome message
    writer.write(WELCOME_MESSAGE.encode())
    await writer.drain()

    try:
        # Main client interaction loop - continue until EXIT
        while True:
            # Read client command
            data = await reader.read(MAX_MESSAGE_LENGTH)
            if not data:
                break

            message = data.decode().strip()
            logger.info(f"Received from {addr}: {data}")

            # Check for exit command
            if message.upper() == "EXIT":
                writer.write(b"Goodbye! Thanks for using CaaS!\r\n")
                await writer.drain()
                break

            # Validate command (only allow GET and SET)
            command_parts = message.split(maxsplit=1)
            if not command_parts:
                response = "Error: Empty command"
            elif command_parts[0].upper() not in ["GET", "SET", "DEL"]:
                response = "Error: Only GET, SET and DEL commands are allowed"
            else:
                response = await handle_redis_command(message)

            # Send response back to client
            writer.write(f"{response}\r\n> ".encode())
            await writer.drain()

    except Exception as e:
        logger.error(f"Error handling client {addr}: {e}")
    finally:
        # Close the connection
        writer.close()
        await writer.wait_closed()
        logger.info(f"Connection closed for {addr}")


async def main() -> None:
    """
    Start the TCP server and handle connections.
    """
    server = await asyncio.start_server(handle_client, SERVER_HOST, SERVER_PORT)

    addr = server.sockets[0].getsockname()
    logger.info(f"Server running on {addr}")

    async with server:
        await server.serve_forever()


if __name__ == "__main__":
    try:
        asyncio.run(main())
    except KeyboardInterrupt:
        logger.info("Server shutdown by user")
