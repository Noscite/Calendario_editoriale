import asyncio
import logging
import sys

sys.path.insert(0, '/var/www/noscite-calendar/backend')

from app.services.scheduler_service import run_scheduler

logging.basicConfig(
    level=logging.INFO,
    format='%(asctime)s - %(name)s - %(levelname)s - %(message)s'
)

if __name__ == "__main__":
    asyncio.run(run_scheduler())
