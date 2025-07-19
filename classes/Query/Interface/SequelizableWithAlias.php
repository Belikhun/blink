<?php

namespace Blink\Query\Interface;

interface SequelizableWithAlias extends Sequelizable {
	public function getAlias(): ?string;

	public function setAlias(?string $alias = null): static;
}
